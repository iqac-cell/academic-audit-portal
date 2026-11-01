<?php

namespace App\Services\Sso;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** Relying-party side of the Q-Sphere OpenID Connect provider. */
class OidcClient
{
    public const BACKCHANNEL_EVENT = 'http://schemas.openid.net/event/backchannel-logout';

    private ?array $metadata = null;

    public function enabled(): bool
    {
        return (bool) config('sso.enabled');
    }

    public function issuer(): string
    {
        return config('sso.issuer');
    }

    /** Provider metadata; falls back to the endpoints of the agreed contract when discovery fails. */
    public function metadata(): array
    {
        if ($this->metadata !== null) {
            return $this->metadata;
        }
        $defaults = [
            'issuer' => $this->issuer(),
            'authorization_endpoint' => $this->issuer().'/oauth/authorize',
            'token_endpoint' => $this->issuer().'/oauth/token',
            'userinfo_endpoint' => $this->issuer().'/oauth/userinfo',
            'jwks_uri' => $this->issuer().'/oauth/jwks',
            'end_session_endpoint' => $this->issuer().'/oauth/logout',
        ];
        $found = Cache::remember('sso.discovery', config('sso.cache_seconds'), function () {
            try {
                $res = $this->http()->get($this->serverUrl($this->issuer().'/.well-known/openid-configuration'));
                return $res->successful() && is_array($res->json()) ? $res->json() : [];
            } catch (\Throwable $e) {
                report($e);
                return [];
            }
        });

        return $this->metadata = array_merge($defaults, array_intersect_key($found ?: [], $defaults));
    }

    /** Authorization request URL plus the values that must be kept in the session until the callback. */
    public function authorizationRequest(): array
    {
        $state = Str::random(40);
        $nonce = Str::random(40);
        $verifier = Str::random(64);

        $url = $this->metadata()['authorization_endpoint'].'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('sso.client_id'),
            'redirect_uri' => config('sso.redirect_uri'),
            'scope' => config('sso.scopes'),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => Jwt::b64encode(hash('sha256', $verifier, true)),
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);

        return ['url' => $url, 'state' => $state, 'nonce' => $nonce, 'verifier' => $verifier];
    }

    /**
     * Exchange the authorization code and return the verified id_token claims.
     *
     * @throws SsoException
     */
    public function exchangeCode(string $code, string $verifier, string $nonce): array
    {
        $form = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => config('sso.redirect_uri'),
            'client_id' => config('sso.client_id'),
            'code_verifier' => $verifier,
        ];
        if (filled(config('sso.client_secret'))) {
            $form['client_secret'] = config('sso.client_secret');
        }

        try {
            $res = $this->http()->asForm()->post($this->serverUrl($this->metadata()['token_endpoint']), $form);
        } catch (\Throwable $e) {
            throw new SsoException('The login server could not be reached.', 0, $e);
        }
        if (! $res->successful() || ! is_string($res->json('id_token'))) {
            throw new SsoException('The login server did not accept the sign-in ('.($res->json('error') ?? $res->status()).').');
        }

        $claims = $this->verifyIdToken($res->json('id_token'), $nonce);
        if (blank($claims['email'] ?? null) && is_string($res->json('access_token'))) {
            $claims += $this->userinfo($res->json('access_token'));
        }

        return $claims;
    }

    /** @throws SsoException */
    public function verifyIdToken(string $jwt, string $nonce): array
    {
        $claims = $this->verifySigned($jwt);
        if (! isset($claims['exp']) || (int) $claims['exp'] < time() - config('sso.leeway')) {
            throw new SsoException('The sign-in has expired. Please try again.');
        }
        if (! isset($claims['nonce']) || ! hash_equals($nonce, (string) $claims['nonce'])) {
            throw new SsoException('The sign-in could not be matched to this browser (nonce).');
        }
        if (blank($claims['sub'] ?? null)) {
            throw new SsoException('The sign-in did not identify a user.');
        }

        return $claims;
    }

    /**
     * Validate a back-channel logout token (OpenID Connect Back-Channel Logout 1.0, section 2.6).
     *
     * @throws SsoException
     */
    public function verifyLogoutToken(string $jwt): array
    {
        $claims = $this->verifySigned($jwt);
        if (! isset($claims['iat']) || (int) $claims['iat'] > time() + config('sso.leeway')) {
            throw new SsoException('Logout token has no valid iat.');
        }
        if (isset($claims['exp']) && (int) $claims['exp'] < time() - config('sso.leeway')) {
            throw new SsoException('Logout token has expired.');
        }
        if (! is_array($claims['events'] ?? null) || ! array_key_exists(self::BACKCHANNEL_EVENT, $claims['events'])) {
            throw new SsoException('Logout token has no back-channel logout event.');
        }
        if (array_key_exists('nonce', $claims)) {
            throw new SsoException('Logout token must not contain a nonce.');
        }
        if (blank($claims['sub'] ?? null) && blank($claims['sid'] ?? null)) {
            throw new SsoException('Logout token names neither sub nor sid.');
        }
        if (blank($claims['jti'] ?? null)) {
            throw new SsoException('Logout token has no jti.');
        }
        if (! Cache::add('sso.logout-jti.'.sha1($claims['jti']), 1, 600)) {
            throw new SsoException('Logout token was already used.');
        }

        return $claims;
    }

    /** RP-initiated logout at Q-Sphere. */
    public function logoutUrl(): string
    {
        return $this->metadata()['end_session_endpoint'].'?'.http_build_query([
            'post_logout_redirect_uri' => config('sso.post_logout_redirect_uri'),
            'client_id' => config('sso.client_id'),
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** Signature, issuer and audience checks shared by id_token and logout_token. */
    private function verifySigned(string $jwt): array
    {
        $jwks = $this->jwks();
        if (! Jwt::hasKey($jwks, Jwt::kid($jwt))) {
            $jwks = $this->jwks(true); // key rotation
        }
        $claims = Jwt::verify($jwt, $jwks);

        if (($claims['iss'] ?? null) !== $this->issuer()) {
            throw new SsoException('Token was issued by an unexpected server.');
        }
        $aud = (array) ($claims['aud'] ?? []);
        if (! in_array(config('sso.client_id'), $aud, true)) {
            throw new SsoException('Token was issued for another application.');
        }
        if (count($aud) > 1 && ($claims['azp'] ?? null) !== config('sso.client_id')) {
            throw new SsoException('Token was issued for another application.');
        }

        return $claims;
    }

    private function jwks(bool $refresh = false): array
    {
        if ($refresh) {
            Cache::forget('sso.jwks');
        }
        $jwks = Cache::get('sso.jwks');
        if (is_array($jwks)) {
            return $jwks;
        }
        try {
            $res = $this->http()->get($this->serverUrl($this->metadata()['jwks_uri']));
        } catch (\Throwable $e) {
            throw new SsoException('The login server\'s signing keys could not be fetched.', 0, $e);
        }
        if (! $res->successful() || ! is_array($res->json('keys'))) {
            throw new SsoException('The login server\'s signing keys could not be fetched.');
        }
        Cache::put('sso.jwks', $res->json(), config('sso.cache_seconds'));

        return $res->json();
    }

    private function userinfo(string $accessToken): array
    {
        try {
            $res = $this->http()->withToken($accessToken)->get($this->serverUrl($this->metadata()['userinfo_endpoint']));
            return $res->successful() && is_array($res->json()) ? array_intersect_key($res->json(), array_flip(['email', 'name', 'employee_id', 'dept_code', 'office_code', 'qs_roles'])) : [];
        } catch (\Throwable $e) {
            report($e);
            return [];
        }
    }

    /** Swap the public issuer prefix for SSO_SERVER_URL on server-to-server calls. */
    private function serverUrl(string $url): string
    {
        $server = config('sso.server_url');
        return $server && str_starts_with($url, $this->issuer()) ? $server.substr($url, strlen($this->issuer())) : $url;
    }

    private function http(): PendingRequest
    {
        return Http::timeout(config('sso.http_timeout'))->acceptJson()->withOptions(['verify' => config('sso.verify_tls')]);
    }
}
