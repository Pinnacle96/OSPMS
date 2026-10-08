<?php

use App\Domains\Reporting\Services\DashboardDestination;
use App\Http\Controllers\Web\AccountController;
use App\Http\Controllers\Web\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? app(DashboardDestination::class)->route(auth()->user()) : 'login'))->name('home');
require __DIR__.'/auth.php';
require __DIR__.'/public.php';
Route::middleware(['auth', 'active_user', 'password_change'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard.state');
    Route::get('/executive/dashboard', DashboardController::class)->name('dashboard.executive');
    Route::get('/finance/dashboard', DashboardController::class)->name('dashboard.revenue');
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
