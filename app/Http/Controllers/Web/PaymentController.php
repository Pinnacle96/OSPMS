<?php

namespace App\Http\Controllers\Web;

use App\Domains\Finance\Models\FinancialTransaction;
use App\Domains\Finance\Models\Refund;
use App\Domains\Finance\Services\CorrectionViewService;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Payments\Actions\InitiatePaymentAction;
use App\Domains\Payments\Actions\ResolveDemoPaymentAction;
use App\Domains\Payments\Actions\ReversePaymentAction;
use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\Queries\FinancialFilterOptions;
use App\Domains\Payments\Queries\PaymentListQuery;
use App\Domains\Ticketing\Models\Ticket;
use App\Domains\Ticketing\Services\TicketExpiryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\FinancialFilterRequest;
use App\Http\Requests\Payments\ReversePaymentRequest;
use App\Http\Requests\Payments\SimulatePaymentRequest;
use App\Support\Payments\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function index(FinancialFilterRequest $request, PaymentListQuery $query)
    {
        return Inertia::render('Payments/Index', ['records' => $query->get($request->user(), $request->validated()), 'filters' => $request->validated(), ...app(FinancialFilterOptions::class)->get($request->user())]);
    }

    public function show(Request $request, Payment $payment)
    {
        Gate::authorize('view', $payment);
        $t = $payment->ticket;
        $receipt = $payment->receipt;

        return Inertia::render('Payments/Show', [
            'payment' => [...$payment->only(['public_id', 'payment_reference', 'provider', 'provider_reference', 'channel', 'amount', 'currency', 'initiated_at', 'paid_at', 'failed_at', 'reversed_at']), 'status' => $payment->status->value, 'demo' => $payment->provider === 'demo'],
            'ticket' => ['public_id' => $t->public_id, 'ticket_reference' => $t->ticket_reference, 'vehicle' => $t->context_snapshot['vehicle']['registration'] ?? null, 'park' => $t->context_snapshot['park']['name'] ?? null, 'status' => app(TicketExpiryService::class)->status($t)->value],
            'receipt' => $receipt && $request->user()->can('view', $receipt) ? $receipt->only(['public_id', 'receipt_number']) : null,
            'ledger' => $request->user()->can('view_financial_ledger') ? app(UserAccessScopeService::class)->scopeLedger(FinancialTransaction::where('payment_id', $payment->id), $request->user())->get(['public_id', 'transaction_reference', 'direction', 'amount', 'currency']) : [],
            'can_request_refund' => $payment->status->value === 'successful' && $payment->provider === 'demo' && app(PaymentGatewayManager::class)->demoEnabled() && $request->user()->can('request', [Refund::class, $payment]),
            'refunds' => $request->user()->can('view_refund') ? Refund::where('payment_id', $payment->id)->latest('id')->get()->filter(fn ($r) => $request->user()->can('view', $r))->map(fn ($r) => app(CorrectionViewService::class)->record($r))->values() : [],
            'can_resolve' => $payment->status->value === 'pending' && $request->user()->can('pay', $t), 'can_reverse' => $payment->status->value === 'successful' && $request->user()->can('reverse', $payment), 'idempotency_key' => bin2hex(random_bytes(32)),
        ])->toResponse($request)->withHeaders(['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    public function pay(Request $request, Ticket $ticket)
    {
        Gate::authorize('pay', $ticket);

        return Inertia::render('Payments/DemoPay', ['ticket' => ['public_id' => $ticket->public_id, 'ticket_reference' => $ticket->ticket_reference, 'amount' => $ticket->amount, 'currency' => $ticket->currency, 'vehicle' => $ticket->context_snapshot['vehicle']['registration'] ?? null, 'park' => $ticket->context_snapshot['park']['name'] ?? null, 'status' => app(TicketExpiryService::class)->status($ticket)->value, 'payment_status' => $ticket->payment_status->value], 'idempotency_key' => bin2hex(random_bytes(32))]);
    }

    public function store(SimulatePaymentRequest $request, Ticket $ticket, InitiatePaymentAction $action)
    {
        $p = $action->execute($request->user(), $ticket, $request->validated('scenario'), $request->validated('idempotency_key'));

        return to_route('payments.show', $p)->with('success', 'Demo payment outcome recorded. No real funds were charged.');
    }

    public function resolve(SimulatePaymentRequest $request, Payment $payment, ResolveDemoPaymentAction $action)
    {
        $action->execute($request->user(), $payment, $request->validated('scenario'), $request->validated('idempotency_key'));

        return to_route('payments.show', $payment)->with('success', 'Pending demo attempt resolved.');
    }

    public function reverse(ReversePaymentRequest $request, Payment $payment, ReversePaymentAction $action)
    {
        $action->execute($request->user(), $payment, $request->validated('reason'), $request->validated('idempotency_key'));

        return to_route('payments.show', $payment)->with('success', 'Demo payment reversed. Original credit and receipt remain in history.');
    }
}
