<?php

$appUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');

return [
    // Central login through the Q-Sphere IQAC portal (OpenID Connect, authorization code + PKCE S256).
    // When off, /login shows the local email + password form for everyone (as before).
    'enabled' => (bool) env('SSO_ENABLED', false),

    // Must equal the "iss" claim of Q-Sphere's tokens exactly.
    'issuer' => rtrim((string) env('SSO_ISSUER', 'https://sdg.mmmut.ac.in/iqac'), '/'),

    // Base URL for server-to-server calls (discovery, token, JWKS, userinfo). Leave empty to use the issuer.
    // Set it when the server cannot reach its own public name (e.g. https://localhost/iqac).
    'server_url' => rtrim((string) env('SSO_SERVER_URL', ''), '/'),
    'verify_tls' => (bool) env('SSO_VERIFY_TLS', true),

    'client_id' => env('SSO_CLIENT_ID', 'academic-audit'),
    'client_secret' => env('SSO_CLIENT_SECRET'),

    'redirect_uri' => env('SSO_REDIRECT_URI', $appUrl.'/sso/callback'),
    'post_logout_redirect_uri' => env('SSO_POST_LOGOUT_REDIRECT_URI', $appUrl.'/'),

    'scopes' => env('SSO_SCOPES', 'openid email profile'),

    // Clock skew allowed when checking exp / iat, and how long discovery + JWKS are cached.
    'leeway' => (int) env('SSO_LEEWAY', 60),
    'cache_seconds' => (int) env('SSO_CACHE_SECONDS', 3600),
    'http_timeout' => (int) env('SSO_HTTP_TIMEOUT', 10),
];
