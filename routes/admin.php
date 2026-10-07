<?php

use App\Http\Controllers\Web\Admin\RoleController;
use App\Http\Controllers\Web\Admin\UserController;
use App\Http\Controllers\Web\Admin\UserScopeController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/users/{user}/scopes', [UserScopeController::class, 'edit'])->name('users.scopes');
    Route::put('/users/{user}/scopes', [UserScopeController::class, 'update'])->name('users.scopes.update');
    Route::resource('users', UserController::class)->except('destroy');
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('/roles/{role}', [RoleController::class, 'edit'])->name('roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::get('/permissions', [RoleController::class, 'matrix'])->name('permissions.matrix');
});
