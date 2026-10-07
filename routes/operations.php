<?php

use App\Http\Controllers\Web\LgaController;
use App\Http\Controllers\Web\ParkController;
use App\Http\Controllers\Web\RouteController;
use Illuminate\Support\Facades\Route;

Route::get('/lgas/{lga}/dashboard', [LgaController::class, 'dashboard'])->name('dashboard.lga');
Route::get('/parks/{park}/dashboard', [ParkController::class, 'dashboard'])->name('dashboard.park');
Route::put('/parks/{park}/routes', [ParkController::class, 'routes'])->name('parks.routes.update');
Route::resource('lgas', LgaController::class)->except('index');
Route::resource('parks', ParkController::class)->except('index');
Route::resource('routes', RouteController::class)->except('index');
Route::get('/lgas', [LgaController::class, 'index'])->defaults('registry', 'lgas')->name('lgas.index');
Route::get('/parks', [ParkController::class, 'index'])->defaults('registry', 'parks')->name('parks.index');
Route::get('/routes', [RouteController::class, 'index'])->defaults('registry', 'routes')->name('routes.index');
