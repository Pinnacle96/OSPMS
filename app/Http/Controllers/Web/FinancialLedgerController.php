<?php

namespace App\Http\Controllers\Web;

use App\Domains\Finance\Models\FinancialTransaction;
use App\Domains\Finance\Queries\FinancialLedgerQuery;
use App\Domains\Payments\Queries\FinancialFilterOptions;
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

    public function show(Request $request, FinancialTransaction $transaction)
    {
        Gate::authorize('view', $transaction);
        $t = $transaction->ticket;
        $p = $transaction->payment;
        $parent = $transaction->parentTransaction;

        return Inertia::render('Finance/Ledger/Show', ['transaction' => $transaction->only(['public_id', 'transaction_reference', 'transaction_type', 'direction', 'amount', 'currency', 'occurred_at', 'description', 'source']), 'ticket' => $t && $request->user()->can('view', $t) ? $t->only(['public_id', 'ticket_reference']) : null, 'payment' => $p && $request->user()->can('view', $p) ? $p->only(['public_id', 'payment_reference']) : null, 'parent' => $parent && $request->user()->can('view', $parent) ? $parent->only(['public_id', 'transaction_reference']) : null, 'reason' => $transaction->metadata['reason'] ?? null, 'reconciliation' => 'Not yet reconciled; reconciliation workflow is not enabled.'])->toResponse($request)->withHeaders(['Cache-Control' => 'private, no-store']);
    }
}
