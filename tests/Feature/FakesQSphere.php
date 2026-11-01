<?php

namespace Tests\Feature;

use App\Services\Sso\Jwt;
use Illuminate\Support\Facades\Http;

/** A fake Q-Sphere OpenID Connect provider: signs tokens with a test-only key and serves its JWKS. */
trait FakesQSphere
{
    protected string $issuer = 'https://sdg.mmmut.ac.in/iqac';
    protected string $clientId = 'academic-audit';
    protected array $tokenRequests = [];
    protected ?string $nextIdToken = null;

    protected function enableSso(): void
    {
        config([
            'app.url' => 'https://sdg.mmmut.ac.in/academic-audit',
            'sso.enabled' => true,
            'sso.issuer' => $this->issuer,
            'sso.server_url' => '',
            'sso.client_id' => $this->clientId,
            'sso.client_secret' => null,
            'sso.redirect_uri' => 'https://sdg.mmmut.ac.in/academic-audit/sso/callback',
            'sso.post_logout_redirect_uri' => 'https://sdg.mmmut.ac.in/academic-audit/',
        ]);

        Http::fake([
            $this->issuer.'/.well-known/openid-configuration' => Http::response([
                'issuer' => $this->issuer,
                'authorization_endpoint' => $this->issuer.'/oauth/authorize',
                'token_endpoint' => $this->issuer.'/oauth/token',
                'userinfo_endpoint' => $this->issuer.'/oauth/userinfo',
                'jwks_uri' => $this->issuer.'/oauth/jwks',
                'end_session_endpoint' => $this->issuer.'/oauth/logout',
            ]),
            $this->issuer.'/oauth/jwks' => Http::response(['keys' => [$this->jwk('sso-test-key', 'k1')]]),
            $this->issuer.'/oauth/token' => function ($request) {
                $this->tokenRequests[] = $request->data();
                return Http::response(['access_token' => 'at', 'token_type' => 'Bearer', 'expires_in' => 300, 'id_token' => $this->nextIdToken]);
            },
        ]);
    }

    protected function jwk(string $keyFile, string $kid): array
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_private(file_get_contents(base_path("tests/Fixtures/{$keyFile}.pem"))));
        return ['kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256', 'kid' => $kid,
            'n' => Jwt::b64encode($details['rsa']['n']), 'e' => Jwt::b64encode($details['rsa']['e'])];
    }

    protected function signToken(array $claims, string $keyFile = 'sso-test-key', string $kid = 'k1'): string
    {
        $h = Jwt::b64encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $kid]));
        $p = Jwt::b64encode(json_encode($claims));
        openssl_sign("{$h}.{$p}", $sig, openssl_pkey_get_private(file_get_contents(base_path("tests/Fixtures/{$keyFile}.pem"))), OPENSSL_ALGO_SHA256);
        return "{$h}.{$p}.".Jwt::b64encode($sig);
    }

    protected function idTokenClaims(string $nonce, array $overrides = []): array
    {
        return array_merge([
            'iss' => $this->issuer,
            'aud' => $this->clientId,
            'sub' => 'qs-42',
            'iat' => time(),
            'exp' => time() + 300,
            'nonce' => $nonce,
            'email' => 'hod.cse@mmmut.ac.in',
            'name' => 'Prof. HOD CSE',
            'employee_id' => 'E042',
            'dept_code' => 'CSE',
            'office_code' => null,
            'qs_roles' => ['hod'],
            'sid' => 'qs-session-1',
        ], $overrides);
    }

    /** Start the login at /sso/login and return the state + nonce the app sent to Q-Sphere. */
    protected function startLogin(): array
    {
        $res = $this->get('/sso/login');
        $res->assertRedirect();
        parse_str(parse_url($res->headers->get('Location'), PHP_URL_QUERY), $query);
        return $query;
    }

    /** Full browser round trip: start, let Q-Sphere "sign" the given claims, hit the callback. */
    protected function loginWith(array $overrides = [], ?callable $tamper = null)
    {
        $query = $this->startLogin();
        $claims = $this->idTokenClaims($query['nonce'], $overrides);
        $this->nextIdToken = $tamper ? $tamper($claims) : $this->signToken($claims);
        return $this->get('/sso/callback?code=abc123&state='.urlencode($query['state']));
    }
}
