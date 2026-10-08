<?php

namespace App\Http\Controllers\Web;

use App\Domains\Finance\Actions\CreateSettlementAction;
use App\Domains\Finance\Models\Settlement;
use App\Domains\Finance\Services\FinanceListFilter;
use App\Domains\Finance\Services\FinancePeriod;
use App\Domains\Finance\Services\SettlementService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CreateSettlementRequest;
use App\Http\Requests\Finance\FinanceListRequest;
use App\Support\Payments\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class SettlementController extends Controller
{
    public function index(FinanceListRequest $request)
    {
        Gate::authorize('viewAny', Settlement::class);
        $filters = $request->validated();
        $records = Settlement::query()->when($filters['search'] ?? null, fn ($q, $s) => $q->where('settlement_reference', 'like', '%'.$s.'%'))->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s));
        app(FinanceListFilter::class)->dates($records, 'created_at', $filters);
        $records = app(FinanceListFilter::class)->sort($records, $filters, ['settlement_reference', 'gross_amount', 'net_amount', 'settled_at', 'created_at'], 'created_at')->paginate(20)->withQueryString()->through(fn ($s) => $s->only(['public_id', 'settlement_reference', 'provider', 'gross_amount', 'net_amount', 'currency', 'status', 'settled_at']));

        return Inertia::render('Finance/Settlements/Index', ['records' => $records, 'filters' => $filters, 'can_create' => $request->user()->can('create', Settlement::class) && app(PaymentGatewayManager::class)->demoEnabled()]);
    }

    public function create(Request $request)
    {
        Gate::authorize('create', Settlement::class);
        abort_unless(app(PaymentGatewayManager::class)->demoEnabled(), 403);
        $input = $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d']);
        $period = app(FinancePeriod::class)->validate(['from' => $input['from'] ?? now(config('ospm.timezone'))->startOfMonth()->toDateString(), 'to' => $input['to'] ?? now(config('ospm.timezone'))->toDateString()]);
        $eligible = app(SettlementService::class)->eligible($period);

        return Inertia::render('Finance/Settlements/Create', ['period' => array_intersect_key($period, array_flip(['from', 'to'])), 'eligible_count' => (clone $eligible)->count(), 'entries' => $eligible->orderBy('id')->limit(500)->get()->map(fn ($t) => $t->only(['id', 'transaction_reference', 'amount', 'currency', 'occurred_at'])), 'idempotency_key' => bin2hex(random_bytes(32))]);
    }

    public function store(CreateSettlementRequest $request, CreateSettlementAction $action)
    {
        $settlement = $action->execute($request->user(), $request->validated());

        return redirect('/finance/settlements/'.$settlement->public_id)->with('success', 'Demo settlement recorded. No real funds were transferred.');
    }

    public function show(Request $request, Settlement $settlement)
    {
        Gate::authorize('view', $settlement);
        $items = $settlement->items()->with('transaction')->orderBy('id')->paginate(20)->withQueryString()->through(fn ($i) => ['id' => $i->id, 'amount' => $i->amount, 'status' => $i->status, 'transaction_reference' => $i->transaction->transaction_reference, 'ledger_url' => $request->user()->can('view', $i->transaction) ? '/finance/ledger/'.$i->transaction->public_id : null]);

        return Inertia::render('Finance/Settlements/Show', ['settlement' => $settlement->only(['public_id', 'settlement_reference', 'provider', 'provider_settlement_reference', 'period_start', 'period_end', 'gross_amount', 'provider_fees', 'net_amount', 'currency', 'status', 'settled_at', 'government_account_reference']), 'demo' => (bool) ($settlement->metadata['demo'] ?? false), 'items' => $items]);
    }
}
