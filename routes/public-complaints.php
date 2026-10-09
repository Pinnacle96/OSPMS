<?php

use App\Http\Controllers\Web\PublicComplaintController;
use Illuminate\Support\Facades\Route;
use Inertia\EncryptHistoryMiddleware;

Route::middleware(['cache.headers:private;no_store', EncryptHistoryMiddleware::class])->group(function () {
    Route::get('/public/complaints', [PublicComplaintController::class, 'create'])->name('public.complaints.create');
    Route::post('/public/complaints', [PublicComplaintController::class, 'store'])->middleware('throttle:public-complaints')->name('public.complaints.store');
    Route::get('/public/complaints/success', [PublicComplaintController::class, 'success'])->name('public.complaints.success');
});
