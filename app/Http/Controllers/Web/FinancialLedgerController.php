<?php

namespace App\Http\Controllers\Web;

use App\Domains\Finance\Models\FinancialTransaction;
use App\Domains\Finance\Queries\FinancialLedgerQuery;
use App\Domains\Payments\Queries\FinancialFilterOptions;
use App\Domains\Reconciliation\Services\LatestReconciliation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\FinancialFilterRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class FinancialLedgerController extends Controller
{
    public function index(FinancialFilterRequest $request, FinancialLedgerQuery $query)
    {
        return Inertia::render('Finance/Ledger/Index', ['records' => $query->get($request->user(), $request->validated()), 'filters' => $request->validated(), ...app(FinancialFilterOptions::class)->get($request->user())]);
    }

    private function reconciliation(FinancialTransaction $transaction): ?array
    {
        $item = app(LatestReconciliation::class)->query()->where('ticket_id', $transaction->ticket_id)
            ->where(fn ($q) => $q->whereNotNull('payment_id')->orWhereNull('financial_transaction_id'))->latest('id')->first();

        return $item ? ['status' => $item->status->value, 'exception_type' => $item->exception_type, 'url' => '/finance/reconciliation/items/'.$item->id] : ['status' => 'unreconciled', 'exception_type' => null, 'url' => null];
    }

    public function show(Request $request, FinancialTransaction $transaction)
    {
        Gate::authorize('view', $transaction);
        $t = $transaction->ticket;
        $p = $transaction->payment;
        $parent = $transaction->parentTransaction;

        return Inertia::render('Finance/Ledger/Show', ['transaction' => $transaction->only(['public_id', 'transaction_reference', 'transaction_type', 'direction', 'amount', 'currency', 'occurred_at', 'description', 'source']), 'ticket' => $t && $request->user()->can('view', $t) ? $t->only(['public_id', 'ticket_reference']) : null, 'payment' => $p && $request->user()->can('view', $p) ? $p->only(['public_id', 'payment_reference']) : null, 'parent' => $parent && $request->user()->can('view', $parent) ? $parent->only(['public_id', 'transaction_reference']) : null, 'reason' => $transaction->metadata['reason'] ?? null, 'reconciliation' => $request->user()->can('view_reconciliation') ? $this->reconciliation($transaction) : null])->toResponse($request)->withHeaders(['Cache-Control' => 'private, no-store']);
    }
}
