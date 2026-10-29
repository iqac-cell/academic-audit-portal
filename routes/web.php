<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// When the module has its own prefix, send the site root to it. With an empty prefix
// (portal hosted as an IIS sub-application, e.g. sdg.mmmut.ac.in/academic-audit) the
// dashboard itself is the root.
if (config('academic-audit.route_prefix') !== '') {
    Route::redirect('/', '/'.config('academic-audit.route_prefix'));
}

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('password', [AuthController::class, 'showChangePassword'])->name('password.change');
    Route::post('password', [AuthController::class, 'changePassword'])->name('password.update');
});
