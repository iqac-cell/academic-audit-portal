<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [\App\Http\Middleware\ForcePasswordChange::class]);
        $middleware->redirectUsersTo(fn () => route('audit.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // An expired session shows up as a CSRF mismatch (419 "Page Expired").
        // Send the user back to the login screen instead of the bare error page.
        // Laravel turns TokenMismatchException into an HttpException(419) before render callbacks run.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 && ! $e->getPrevious() instanceof TokenMismatchException) {
                return null;
            }
            $message = 'Your session expired. Please log in again.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'redirect' => route('login', ['expired' => 1])], 419);
            }
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->with('expired', $message);
        });
    })->create();
