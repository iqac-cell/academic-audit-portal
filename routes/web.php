<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
use Illuminate\Support\Facades\Route;

// When the module has its own prefix, send the site root to it. With an empty prefix
// (portal hosted as an IIS sub-application, e.g. sdg.mmmut.ac.in/academic-audit) the
// dashboard itself is the root.
if (config('academic-audit.route_prefix') !== '') {
    Route::redirect('/', '/'.config('academic-audit.route_prefix'));
} elseif ($subPath = trim((string) parse_url(config('app.url'), PHP_URL_PATH), '/')) {
    // Under a sub-application IIS may hand over the application root as ".../academic-audit"
    // (with or without slash, depending on the parent site's rewrite rules). Serve the
    // dashboard directly instead of redirecting, so no redirect loop can form.
    Route::get($subPath, [\Mmmut\AcademicAudit\Http\Controllers\DashboardController::class, 'index'])
        ->middleware(array_diff(config('academic-audit.middleware'), ['web']));
}

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'send'])->middleware('throttle:5,10')->name('password.email');
    Route::get('forgot-password/verify', [PasswordResetController::class, 'verifyForm'])->name('password.otp.verify');
    Route::post('forgot-password/verify', [PasswordResetController::class, 'reset'])->middleware('throttle:10,10')->name('password.otp.reset');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('password', [AuthController::class, 'showChangePassword'])->name('password.change');
    Route::post('password', [AuthController::class, 'changePassword'])->name('password.update');
});
