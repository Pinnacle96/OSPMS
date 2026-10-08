<?php

use App\Http\Controllers\Web\AdjustmentController;
use App\Http\Controllers\Web\FinancialLedgerController;
use App\Http\Controllers\Web\PaymentController;
use App\Http\Controllers\Web\ReceiptController;
use App\Http\Controllers\Web\ReconciliationController;
use App\Http\Controllers\Web\RefundController;
use App\Http\Controllers\Web\SettlementController;
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

Route::middleware('cache.headers:private;no_store')->group(function () {
    Route::get('/payments/{payment}/refund', [RefundController::class, 'create'])->name('finance.refunds.create');
    Route::post('/payments/{payment}/refund', [RefundController::class, 'store'])->name('finance.refunds.store');
    Route::get('/finance/refunds', [RefundController::class, 'index'])->name('finance.refunds.index');
    Route::get('/finance/refunds/{refund}', [RefundController::class, 'show'])->name('finance.refunds.show');
    Route::post('/finance/refunds/{refund}/review', [RefundController::class, 'review'])->name('finance.refunds.review');
    Route::post('/finance/refunds/{refund}/process', [RefundController::class, 'process'])->name('finance.refunds.process');
    Route::get('/finance/adjustments', [AdjustmentController::class, 'index'])->name('finance.adjustments.index');
    Route::get('/finance/adjustments/create', [AdjustmentController::class, 'create'])->name('finance.adjustments.create');
    Route::post('/finance/adjustments', [AdjustmentController::class, 'store'])->name('finance.adjustments.store');
    Route::get('/finance/adjustments/{adjustment}', [AdjustmentController::class, 'show'])->name('finance.adjustments.show');
    Route::post('/finance/adjustments/{adjustment}/review', [AdjustmentController::class, 'review'])->name('finance.adjustments.review');
    Route::get('/finance/settlements', [SettlementController::class, 'index'])->name('finance.settlements.index');
    Route::get('/finance/settlements/create', [SettlementController::class, 'create'])->name('finance.settlements.create');
    Route::post('/finance/settlements', [SettlementController::class, 'store'])->name('finance.settlements.store');
    Route::get('/finance/settlements/{settlement}', [SettlementController::class, 'show'])->name('finance.settlements.show');
    Route::get('/finance/reconciliation', [ReconciliationController::class, 'dashboard'])->name('finance.reconciliation.dashboard');
    Route::get('/finance/reconciliation/runs', [ReconciliationController::class, 'index'])->name('finance.reconciliation.index');
    Route::get('/finance/reconciliation/runs/create', [ReconciliationController::class, 'create'])->name('finance.reconciliation.create');
    Route::post('/finance/reconciliation/runs', [ReconciliationController::class, 'store'])->name('finance.reconciliation.store');
    Route::get('/finance/reconciliation/runs/{run}', [ReconciliationController::class, 'show'])->name('finance.reconciliation.show');
    Route::get('/finance/reconciliation/items/{item}', [ReconciliationController::class, 'exception'])->name('finance.reconciliation.exception');
    Route::post('/finance/reconciliation/items/{item}/resolve', [ReconciliationController::class, 'resolve'])->name('finance.reconciliation.resolve');
});
