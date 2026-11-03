<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Browser security headers on every response. Headers already set (e.g. by IIS) are kept. */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $headers = $response->headers;

        $defaults = [
            'X-Frame-Options' => 'SAMEORIGIN',
            'Content-Security-Policy' => "frame-ancestors 'self'",
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ];
        if (app()->isProduction() && $request->isSecure()) {
            $defaults['Strict-Transport-Security'] = 'max-age=31536000';
        }
        foreach ($defaults as $name => $value) {
            if (! $headers->has($name)) {
                $headers->set($name, $value);
            }
        }

        return $response;
    }
}
