<?php

use App\Http\Controllers\Field\FieldDriverController;
use App\Http\Controllers\Field\FieldHomeController;
use App\Http\Controllers\Field\FieldInspectionController;
use App\Http\Controllers\Field\FieldOperatorController;
use App\Http\Controllers\Field\FieldVehicleController;
use App\Http\Controllers\Field\FieldVerificationController;
use Illuminate\Support\Facades\Route;
use Inertia\EncryptHistoryMiddleware;

Route::prefix('field')->name('field.')->middleware(['can:access_field', 'cache.headers:private;no_store', EncryptHistoryMiddleware::class])->group(function () {
    Route::get('/', FieldHomeController::class)->name('home');
    Route::get('/scan', [FieldVerificationController::class, 'scan'])->middleware('can:verify_ticket')->name('scan');
    Route::get('/verify/{token}', [FieldVerificationController::class, 'show'])->middleware(['can:verify_ticket', 'throttle:field-verification'])->name('verify');
    foreach (['drivers' => FieldDriverController::class, 'vehicles' => FieldVehicleController::class, 'operators' => FieldOperatorController::class] as $plural => $controller) {
        Route::get('/'.$plural, [$controller, 'index'])->middleware(['can:field_lookup', 'throttle:field-lookup'])->name($plural.'.search');
        Route::get('/'.$plural.'/{'.rtrim($plural, 's').'}', [$controller, 'show'])->middleware('can:field_lookup')->name($plural.'.show');
    }
    Route::get('/inspections/create', [FieldInspectionController::class, 'create'])->name('inspections.create');
    Route::post('/inspections', [FieldInspectionController::class, 'store'])->middleware('throttle:field-inspections')->name('inspections.store');
});
