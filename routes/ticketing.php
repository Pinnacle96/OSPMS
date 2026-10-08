<?php

use App\Http\Controllers\Web\TicketController;
use Illuminate\Support\Facades\Route;

Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create');
Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
Route::get('/tickets/{ticket}/cancel', [TicketController::class, 'cancel'])->name('tickets.cancel');
Route::patch('/tickets/{ticket}/cancel', [TicketController::class, 'close'])->name('tickets.close');
Route::get('/tickets/{ticket}/print', [TicketController::class, 'print'])->name('tickets.print');
Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
