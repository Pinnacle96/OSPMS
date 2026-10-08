<?php

use App\Http\Controllers\Web\AccountController;
use App\Http\Controllers\Web\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? (auth()->user()->can('view_state_dashboard') ? 'dashboard.state' : 'account.access') : 'login'))->name('home');
require __DIR__.'/auth.php';
require __DIR__.'/public.php';
Route::middleware(['auth', 'active_user', 'password_change'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard.state');
    Route::get('/account/profile', [AccountController::class, 'profile'])->name('account.profile');
    Route::patch('/account/profile', [AccountController::class, 'update'])->name('account.profile.update');
    Route::get('/account/security', [AccountController::class, 'security'])->name('account.security');
    Route::put('/account/security/password', [AccountController::class, 'password'])->name('account.password.update');
    Route::delete('/account/security/sessions', [AccountController::class, 'revoke'])->name('account.sessions.destroy');
    Route::get('/account/access', [AccountController::class, 'access'])->name('account.access');
    require __DIR__.'/admin.php';
    require __DIR__.'/operations.php';
    require __DIR__.'/registry.php';
    require __DIR__.'/ticketing.php';
    require __DIR__.'/finance.php';
});
