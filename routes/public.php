<?php

use App\Http\Controllers\Field\FieldServiceWorkerController;
use App\Http\Controllers\Public\ReceiptVerificationController;
use App\Http\Controllers\Public\TicketVerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/verify/ticket/{token}', TicketVerificationController::class)->middleware('throttle:ticket-verification')->name('public.ticket.verify');

Route::get('/verify/receipt/{token}', ReceiptVerificationController::class)->middleware('throttle:receipt-verification')->name('public.receipt.verify');

// Anonymous static worker; all field business routes require authentication.
Route::get('/field/sw.js', FieldServiceWorkerController::class)->name('field.service-worker');
