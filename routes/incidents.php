<?php

use App\Http\Controllers\Web\EvidenceController;
use App\Http\Controllers\Web\IncidentController;
use App\Http\Controllers\Web\InspectionController;
use App\Http\Controllers\Web\ViolationController;
use Illuminate\Support\Facades\Route;
use Inertia\EncryptHistoryMiddleware;

Route::middleware(['cache.headers:private;no_store', EncryptHistoryMiddleware::class])->group(function () {
    Route::get('/field/incidents/create', [IncidentController::class, 'create'])->middleware('can:access_field')->name('field.incidents.create');
    Route::post('/field/incidents', [IncidentController::class, 'store'])->middleware(['can:access_field', 'throttle:operational-writes'])->name('field.incidents.store');
    Route::get('/inspections', [InspectionController::class, 'index'])->name('inspections.index');
    Route::get('/inspections/{inspection}', [InspectionController::class, 'show'])->name('inspections.show');
    Route::post('/inspections/{inspection}/violations', [ViolationController::class, 'store'])->middleware('throttle:operational-writes')->name('violations.store');
    Route::get('/violations', [ViolationController::class, 'index'])->name('violations.index');
    Route::get('/violations/{violation}', [ViolationController::class, 'show'])->name('violations.show');
    Route::post('/violations/{violation}/resolve', [ViolationController::class, 'resolve'])->middleware('throttle:operational-writes')->name('violations.resolve');
    Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::get('/incidents/create', [IncidentController::class, 'create'])->name('incidents.create');
    Route::post('/incidents', [IncidentController::class, 'store'])->middleware('throttle:operational-writes')->name('incidents.store');
    Route::get('/incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
    Route::get('/incidents/{incident}/manage', [IncidentController::class, 'manage'])->name('incidents.manage');
    Route::post('/incidents/{incident}/manage', [IncidentController::class, 'update'])->middleware('throttle:operational-writes')->name('incidents.update');
    Route::post('/{kind}/{record}/evidence', [EvidenceController::class, 'store'])->whereIn('kind', ['incidents', 'inspections', 'violations', 'complaints'])->middleware('throttle:operational-writes')->name('evidence.store');
    Route::get('/{kind}/{record}/evidence/{media}', [EvidenceController::class, 'show'])->whereIn('kind', ['incidents', 'inspections', 'violations', 'complaints'])->name('evidence.show');
});
