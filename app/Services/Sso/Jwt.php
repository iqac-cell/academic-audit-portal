<?php

namespace App\Services\Sso;

/** Minimal RS256 JWT verification against a JSON Web Key Set (no external library). */
class Jwt
{
    /**
     * Verify the signature of a compact JWT and return its claims.
     *
     * @param  array  $jwks  ['keys' => [...]] as served by the provider's jwks_uri
     * @throws SsoException
     */
    public static function verify(string $jwt, array $jwks): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new SsoException('Malformed token.');
        }
        [$h, $p, $s] = $parts;
        $header = json_decode(self::b64decode($h), true);
        $claims = json_decode(self::b64decode($p), true);
        if (! is_array($header) || ! is_array($claims)) {
            throw new SsoException('Malformed token.');
        }
        if (($header['alg'] ?? null) !== 'RS256') {
            throw new SsoException('Unexpected token algorithm.');
        }

        $key = self::findKey($jwks, $header['kid'] ?? null);
        $ok = openssl_verify("{$h}.{$p}", self::b64decode($s), self::jwkToPem($key), OPENSSL_ALGO_SHA256);
        if ($ok !== 1) {
            throw new SsoException('Token signature is not valid.');
        }

        return $claims;
    }

    public static function kid(string $jwt): ?string
    {
        $header = json_decode(self::b64decode(explode('.', $jwt)[0] ?? ''), true);
        return is_array($header) ? ($header['kid'] ?? null) : null;
    }

    public static function hasKey(array $jwks, ?string $kid): bool
    {
        try {
            self::findKey($jwks, $kid);
            return true;
        } catch (SsoException) {
            return false;
        }
    }

    private static function findKey(array $jwks, ?string $kid): array
    {
        $keys = array_values(array_filter($jwks['keys'] ?? [], fn ($k) => ($k['kty'] ?? null) === 'RSA'
            && ($k['use'] ?? 'sig') === 'sig' && in_array($k['alg'] ?? 'RS256', ['RS256'], true)));
        if ($kid !== null) {
            $keys = array_values(array_filter($keys, fn ($k) => ($k['kid'] ?? null) === $kid));
        }
        if (count($keys) === 0 || ($kid === null && count($keys) > 1)) {
            throw new SsoException('No matching signing key.');
        }
        return $keys[0];
    }

    /** RSA JWK (n, e) → PEM SubjectPublicKeyInfo. */
    public static function jwkToPem(array $jwk): string
    {
        $n = self::b64decode($jwk['n'] ?? '');
        $e = self::b64decode($jwk['e'] ?? '');
        if ($n === '' || $e === '') {
            throw new SsoException('Signing key is incomplete.');
        }
        $rsaKey = self::der(0x30, self::derInteger($n).self::derInteger($e));
        $algorithm = self::der(0x30, self::der(0x06, "\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01").self::der(0x05, ''));
        $spki = self::der(0x30, $algorithm.self::der(0x03, "\x00".$rsaKey));

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($spki), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    private static function derInteger(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '' || ord($bytes[0]) > 0x7f) {
            $bytes = "\x00".$bytes;
        }
        return self::der(0x02, $bytes);
    }

    private static function der(int $tag, string $value): string
    {
        $len = strlen($value);
        if ($len < 0x80) {
            return chr($tag).chr($len).$value;
        }
        $lenBytes = ltrim(pack('N', $len), "\x00");
        return chr($tag).chr(0x80 | strlen($lenBytes)).$lenBytes.$value;
    }

    public static function b64decode(string $s): string
    {
        $decoded = base64_decode(strtr($s, '-_', '+/').str_repeat('=', (4 - strlen($s) % 4) % 4), true);
        return $decoded === false ? '' : $decoded;
    }

    public static function b64encode(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }
}
