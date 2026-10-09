<?php

use App\Http\Controllers\Web\ComplaintController;
use App\Http\Controllers\Web\NotificationController;
use Illuminate\Support\Facades\Route;
use Inertia\EncryptHistoryMiddleware;

Route::middleware(['cache.headers:private;no_store', EncryptHistoryMiddleware::class])->group(function () {
    Route::get('/complaints', [ComplaintController::class, 'index'])->name('complaints.index');
    Route::get('/complaints/create', [ComplaintController::class, 'create'])->name('complaints.create');
    Route::post('/complaints', [ComplaintController::class, 'store'])->middleware('throttle:operational-writes')->name('complaints.store');
    Route::get('/complaints/{complaint}', [ComplaintController::class, 'show'])->name('complaints.show');
    Route::post('/complaints/{complaint}/manage', [ComplaintController::class, 'update'])->middleware('throttle:operational-writes')->name('complaints.manage');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->middleware('throttle:operational-writes')->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->whereUuid('notification')->middleware('throttle:operational-writes')->name('notifications.read');
    Route::get('/account/notifications', [NotificationController::class, 'preferences'])->name('account.notifications');
});
