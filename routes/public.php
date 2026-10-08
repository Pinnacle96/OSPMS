<?php

use App\Http\Controllers\Public\TicketVerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/verify/ticket/{token}', TicketVerificationController::class)->middleware('throttle:ticket-verification')->name('public.ticket.verify');
