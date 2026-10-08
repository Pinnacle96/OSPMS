<?php

namespace App\Http\Controllers\Web;

use App\Domains\Finance\Actions\ApproveRefundAction;
use App\Domains\Finance\Actions\ProcessRefundAction;
use App\Domains\Finance\Actions\RequestRefundAction;
use App\Domains\Finance\Models\FinancialTransaction;
use App\Domains\Finance\Models\Refund;
use App\Domains\Finance\Services\CorrectionViewService;
use App\Domains\Finance\Services\FinanceListFilter;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Payments\Models\Payment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CorrectionListRequest;
use App\Http\Requests\Finance\RequestRefundRequest;
use App\Http\Requests\Finance\ReviewCorrectionRequest;
use App\Support\Payments\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class RefundController extends Controller
{
    public function index(CorrectionListRequest $request)
    {
        $filters = $request->validated();
        $actor = $request->user();
        $q = Refund::whereHas('ticket', fn ($q) => app(UserAccessScopeService::class)->scopeTickets($q, $actor))->when($filters['search'] ?? null, fn ($q, $s) => $q->where('refund_reference', 'like', '%'.$s.'%'))->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s));
        $f = app(FinanceListFilter::class);
        $f->dates($q, 'requested_at', $filters);
        $records = $f->sort($q, $filters, ['requested_at', 'amount', 'refund_reference'], 'requested_at')->paginate(20)->withQueryString()->through(fn ($r) => app(CorrectionViewService::class)->record($r));

        return Inertia::render('Finance/Refunds/Index', ['records' => $records, 'filters' => $filters]);
    }

    public function create(Request $request, Payment $payment)
    {
        Gate::authorize('request', [Refund::class, $payment]);
        app(PaymentGatewayManager::class)->gateway();
        abort_unless($payment->status->value === 'successful' && $payment->provider === 'demo', 422, 'Only successful demo payments can be refunded.');

        return Inertia::render('Finance/Refunds/Create', ['source' => $payment->only(['public_id', 'payment_reference', 'amount', 'currency']), 'idempotency_key' => bin2hex(random_bytes(32))]);
    }

    public function store(RequestRefundRequest $request, Payment $payment, RequestRefundAction $action)
    {
        $r = $action->execute($request->user(), $payment, $request->validated());

        return redirect('/finance/refunds/'.$r->public_id)->with('success', 'Refund requested for independent approval.');
    }

    public function show(Request $request, Refund $refund)
    {
        Gate::authorize('view', $refund);
        $p = $refund->payment;
        $actor = $request->user();
        $entry = FinancialTransaction::where('transaction_reference', 'REF-'.$refund->refund_reference)->first();

        return Inertia::render('Finance/Refunds/Show', ['record' => app(CorrectionViewService::class)->record($refund), 'source' => ['reference' => $p->payment_reference, 'url' => $actor->can('view', $p) ? '/payments/'.$p->public_id : null], 'ledger_url' => $entry && $actor->can('view', $entry) ? '/finance/ledger/'.$entry->public_id : null, 'can_review' => $refund->status->value === 'requested' && $actor->can('approve', $refund), 'can_process' => in_array($refund->status->value, ['approved', 'processing', 'failed']) && $actor->can('process', $refund) && app(PaymentGatewayManager::class)->demoEnabled(), 'history' => app(CorrectionViewService::class)->history($refund), 'idempotency_key' => bin2hex(random_bytes(32))]);
    }

    public function review(ReviewCorrectionRequest $request, Refund $refund, ApproveRefundAction $action)
    {
        $d = $request->validated();
        $action->execute($request->user(), $refund, $d['decision'], $d['reason'], $d['idempotency_key']);

        return back()->with('success', 'Refund review recorded.');
    }

    public function process(ReviewCorrectionRequest $request, Refund $refund, ProcessRefundAction $action)
    {
        $d = $request->validated();
        $action->execute($request->user(), $refund, $d['scenario'], $d['reason'], $d['idempotency_key']);

        return back()->with('success', 'Demo refund outcome recorded. No real funds were transferred.');
    }
}
