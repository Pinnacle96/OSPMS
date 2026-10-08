<?php

use App\Http\Controllers\Web\FinancialLedgerController;
use App\Http\Controllers\Web\PaymentController;
use App\Http\Controllers\Web\ReceiptController;
use Illuminate\Support\Facades\Route;

Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
Route::get('/tickets/{ticket}/pay', [PaymentController::class, 'pay'])->name('payments.demo');
Route::post('/tickets/{ticket}/pay', [PaymentController::class, 'store'])->name('payments.store');
Route::post('/payments/{payment}/simulate', [PaymentController::class, 'resolve'])->name('payments.resolve');
Route::post('/payments/{payment}/reverse', [PaymentController::class, 'reverse'])->name('payments.reverse');
Route::get('/receipts/{receipt}', [ReceiptController::class, 'show'])->name('receipts.show');
Route::get('/receipts/{receipt}/print', [ReceiptController::class, 'print'])->name('receipts.print');
Route::get('/receipts/{receipt}/pdf', [ReceiptController::class, 'pdf'])->name('receipts.pdf');
Route::get('/finance/ledger', [FinancialLedgerController::class, 'index'])->name('finance.ledger.index');
Route::get('/finance/ledger/{transaction}', [FinancialLedgerController::class, 'show'])->name('finance.ledger.show');
