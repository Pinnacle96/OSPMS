<?php

use App\Http\Controllers\Web\ReportController;
use Illuminate\Support\Facades\Route;
use Inertia\EncryptHistoryMiddleware;

Route::middleware(['cache.headers:private;no_store', EncryptHistoryMiddleware::class])->group(function () {
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/exports', [ReportController::class, 'exports'])->name('reports.exports');
    Route::get('/reports/exports/{export}/download', [ReportController::class, 'download'])->whereUlid('export')->name('reports.download');
    Route::post('/reports/exports/{export}/retry', [ReportController::class, 'retry'])->whereUlid('export')->middleware('throttle:10,1')->name('reports.retry');
    Route::get('/reports/{reportType}', [ReportController::class, 'show'])->name('reports.show');
    Route::post('/reports/{reportType}/exports', [ReportController::class, 'store'])->middleware('throttle:10,1')->name('reports.store');

});
