<?php

namespace App\Http\Controllers\Web;

use App\Domains\Finance\Actions\ApproveAdjustmentAction;
use App\Domains\Finance\Actions\RequestAdjustmentAction;
use App\Domains\Finance\Models\FinancialAdjustment;
use App\Domains\Finance\Models\FinancialTransaction;
use App\Domains\Finance\Services\CorrectionViewService;
use App\Domains\Finance\Services\FinanceListFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CorrectionListRequest;
use App\Http\Requests\Finance\RequestAdjustmentRequest;
use App\Http\Requests\Finance\ReviewCorrectionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AdjustmentController extends Controller
{
    public function index(CorrectionListRequest $request)
    {
        $filters = $request->validated();
        $q = FinancialAdjustment::whereHas('originalTransaction', fn ($q) => $q->whereHas('ticket'))->when($filters['search'] ?? null, fn ($q, $s) => $q->where('adjustment_reference', 'like', '%'.$s.'%'))->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s));
        $f = app(FinanceListFilter::class);
        $f->dates($q, 'requested_at', $filters);

        return Inertia::render('Finance/Adjustments/Index', ['records' => $f->sort($q, $filters, ['requested_at', 'amount', 'adjustment_reference'], 'requested_at')->paginate(20)->withQueryString()->through(fn ($r) => app(CorrectionViewService::class)->record($r)), 'filters' => $filters, 'can_create' => $request->user()->can('create', FinancialAdjustment::class)]);
    }

    public function create(Request $request)
    {
        Gate::authorize('create', FinancialAdjustment::class);
        $d = $request->validate(['original_transaction' => 'nullable|string|size:26']);
        $source = null;
        if ($d['original_transaction'] ?? null) {
            $t = FinancialTransaction::where('public_id', $d['original_transaction'])->firstOrFail();
            Gate::authorize('request', [FinancialAdjustment::class, $t]);
            $source = $t->only(['public_id', 'transaction_reference', 'amount', 'currency', 'direction']);
        }

        return Inertia::render('Finance/Adjustments/Create', ['source' => $source, 'idempotency_key' => bin2hex(random_bytes(32))]);
    }

    public function store(RequestAdjustmentRequest $request, RequestAdjustmentAction $action)
    {
        $d = $request->validated();
        $t = FinancialTransaction::where('public_id', $d['original_transaction'])->firstOrFail();
        $a = $action->execute($request->user(), $t, $d);

        return redirect('/finance/adjustments/'.$a->public_id)->with('success', 'Adjustment requested for independent approval.');
    }

    public function show(Request $request, FinancialAdjustment $adjustment)
    {
        Gate::authorize('view', $adjustment);
        $t = $adjustment->originalTransaction;
        $actor = $request->user();
        $entry = FinancialTransaction::where('transaction_reference', 'ADJ-'.$adjustment->adjustment_reference)->first();

        return Inertia::render('Finance/Adjustments/Show', ['record' => app(CorrectionViewService::class)->record($adjustment), 'source' => ['reference' => $t->transaction_reference, 'url' => $actor->can('view', $t) ? '/finance/ledger/'.$t->public_id : null], 'ledger_url' => $entry && $actor->can('view', $entry) ? '/finance/ledger/'.$entry->public_id : null, 'can_review' => $adjustment->status->value === 'requested' && $actor->can('approve', $adjustment), 'can_process' => false, 'history' => app(CorrectionViewService::class)->history($adjustment), 'idempotency_key' => bin2hex(random_bytes(32))]);
    }

    public function review(ReviewCorrectionRequest $request, FinancialAdjustment $adjustment, ApproveAdjustmentAction $action)
    {
        $d = $request->validated();
        $action->execute($request->user(), $adjustment, $d['decision'], $d['reason'], $d['idempotency_key']);

        return back()->with('success', 'Adjustment review recorded. Approved corrections have a new ledger entry.');
    }
}
