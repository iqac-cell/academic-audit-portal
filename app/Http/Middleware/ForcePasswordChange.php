<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Accounts created from the ERP import must set their own password on first login. */
class ForcePasswordChange
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && $user->must_change_password && ! $request->routeIs('password.*', 'logout')) {
            return redirect()->route('password.change');
        }
        return $next($request);
    }
}
