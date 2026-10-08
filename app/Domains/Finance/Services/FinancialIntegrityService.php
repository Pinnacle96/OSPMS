<?php

namespace App\Domains\Finance\Services;

use App\Domains\Audit\Services\FinancialAuditService;
use App\Domains\Finance\Models\FinancialAdjustment;
use App\Domains\Finance\Models\FinancialTransaction;
use App\Domains\Finance\Models\Refund;
use App\Domains\Identity\Models\User;
use App\Domains\Payments\Models\Payment;
use App\Domains\Ticketing\Models\Ticket;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FinancialIntegrityService
{
    public function input(array $input): array
    {
        $input['reason'] = trim($input['reason'] ?? '');

        return Validator::make($input, ['amount' => ['required', 'string', 'regex:/\A(?:0|[1-9][0-9]{0,12})(?:\.[0-9]{1,2})?\z/D'], 'reason' => 'required|string|min:10|max:2000', 'idempotency_key' => 'required|string|size:64'])->after(function ($v) use ($input) {
            if (isset($input['amount']) && is_string($input['amount']) && preg_match('/\A(?:0|[1-9][0-9]{0,12})(?:\.[0-9]{1,2})?\z/D', $input['amount']) && BigDecimal::of($input['amount'])->isLessThanOrEqualTo(0)) {
                $v->errors()->add('amount', 'Enter a positive amount.');
            }
        })->validate();
    }

    public function reason(string $reason): string
    {
        $reason = trim($reason);
        Validator::make(['reason' => $reason], ['reason' => 'required|string|min:10|max:2000'])->validate();

        return $reason;
    }

    public function lock(Payment $p): Payment
    {
        Ticket::whereKey($p->ticket_id)->lockForUpdate()->firstOrFail();
        $p = Payment::whereKey($p->id)->lockForUpdate()->firstOrFail();
        $credit = FinancialTransaction::where('payment_id', $p->id)->where('transaction_type', 'payment')->where('direction', 'credit')->lockForUpdate()->get();
        if ($credit->count() !== 1 || $credit[0]->ticket_id !== $p->ticket_id || $credit[0]->currency !== $p->currency || ! BigDecimal::of($credit[0]->amount)->isEqualTo($p->amount)) {
            $this->fail('Payment ledger lineage is incomplete or inconsistent.');
        }

        return $p;
    }

    public function remaining(Payment $p, ?int $excludeRefund = null, ?int $excludeAdjustment = null): array
    {
        $net = BigDecimal::zero();
        foreach (FinancialTransaction::where('payment_id', $p->id)->orderBy('id')->lockForUpdate()->get() as $t) {
            if ($t->currency !== $p->currency || $t->ticket_id !== $p->ticket_id) {
                $this->fail('Ledger currency or ticket lineage is inconsistent.');
            }
            $net = $t->direction === 'credit' ? $net->plus($t->amount) : $net->minus($t->amount);
        }
        $refunded = BigDecimal::zero();
        $refundReserved = BigDecimal::zero();
        foreach (Refund::where('payment_id', $p->id)->orderBy('id')->lockForUpdate()->get() as $r) {
            if ($r->status->value === 'successful') {
                $refunded = $refunded->plus($r->amount);
            }
            if ($r->id !== $excludeRefund && in_array($r->status->value, ['requested', 'approved', 'processing'])) {
                $net = $net->minus($r->amount);
                $refundReserved = $refundReserved->plus($r->amount);
            }
        }
        $reserved = BigDecimal::zero();
        foreach (FinancialAdjustment::whereIn('original_transaction_id', FinancialTransaction::where('payment_id', $p->id)->select('id'))->orderBy('id')->lockForUpdate()->get() as $a) {
            if ($a->id !== $excludeAdjustment && $a->status->value === 'requested' && $a->adjustment_type->value === 'debit') {
                $reserved = $reserved->plus($a->amount);
            }
        }
        $net = $net->minus($reserved);
        $provider = BigDecimal::of($p->amount)->minus($refunded)->minus($refundReserved);

        return ['balance' => (string) $net->toScale(2), 'refundable' => (string) BigDecimal::min($provider, $net)->toScale(2), 'refunded' => (string) $refunded->toScale(2)];
    }

    public function fits(string $amount, string $remaining): void
    {
        if (BigDecimal::of($amount)->isGreaterThan($remaining)) {
            $this->fail('Amount exceeds the available balance after retained corrections and pending requests.');
        }
    }

    public function fail(string $message): never
    {
        throw ValidationException::withMessages(['amount' => $message]);
    }

    public function audit(User $actor, Model $record, string $event, string $reason, array $extra = []): void
    {
        $ref = $record instanceof Refund ? $record->refund_reference : $record->adjustment_reference;
        $payload = ['reason' => $reason, 'status' => $record->status->value, ...$extra];
        app(FinancialAuditService::class)->recordEntity($actor, $record, $event, $ref, $record->amount, $record->currency, $payload);
        activity('financial_corrections')->causedBy($actor)->performedOn($record)->withProperties($payload)->log($event);
    }
}
