<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Audit\Services\FinancialAuditService;
use App\Domains\Finance\Models\FinancialTransaction;
use App\Domains\Finance\Models\Settlement;
use App\Domains\Finance\Models\SettlementItem;
use App\Domains\Finance\Services\FinancePeriod;
use App\Domains\Finance\Services\FinancialConfirmationService;
use App\Domains\Finance\Services\SettlementService;
use App\Domains\Identity\Models\User;
use App\Domains\Payments\Models\Payment;
use App\Domains\Ticketing\Models\Ticket;
use App\Support\Payments\PaymentGatewayManager;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateSettlementAction
{
    public function execute(User $actor, array $input): Settlement
    {
        Gate::forUser($actor)->authorize('create', Settlement::class);
        abort_unless(app(PaymentGatewayManager::class)->demoEnabled(), 403, 'Demo settlements are disabled.');
        $period = app(FinancePeriod::class)->validate($input);
        $data = Validator::make($input, ['transaction_ids' => 'required|array|min:1|max:500', 'transaction_ids.*' => 'required|integer|distinct', 'scenario' => 'required|in:matched,amount_mismatch', 'idempotency_key' => 'required|string'])->validate();
        $ids = array_map('intval', $data['transaction_ids']);
        sort($ids);

        return app(FinancialConfirmationService::class)->execute($actor, $data['idempotency_key'], 'settlement.create', ['from' => $period['from'], 'to' => $period['to'], 'ids' => $ids, 'scenario' => $data['scenario']], Settlement::class, function () use ($actor, $period, $data, $ids) {
            // Same ticket -> payment -> ledger order as payment/reversal actions.
            $sources = FinancialTransaction::whereIn('id', $ids)->orderBy('id')->get();
            Ticket::whereIn('id', $sources->pluck('ticket_id'))->orderBy('id')->lockForUpdate()->get();
            $payments = Payment::whereIn('id', $sources->pluck('payment_id'))->orderBy('id')->lockForUpdate()->get();
            $batched = SettlementItem::whereIn('financial_transaction_id', $ids)->lockForUpdate()->get();
            if ($batched->isNotEmpty() || $payments->contains(fn ($p) => $p->status->value !== 'successful' || $p->provider !== 'demo')) {
                throw ValidationException::withMessages(['transaction_ids' => 'An entry was already batched or reversed. Refresh the selection.']);
            }
            $entries = app(SettlementService::class)->eligible($period)->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            if ($entries->count() !== count($ids)) {
                throw ValidationException::withMessages(['transaction_ids' => 'An entry is outside this period, reversed, or already included in a settlement. Refresh the selection.']);
            }
            $amounts = $entries->map(fn ($e, $index) => (string) BigDecimal::of($e->amount)->minus($data['scenario'] === 'amount_mismatch' && $index === 0 ? '0.01' : '0.00')->toScale(2));
            $gross = BigDecimal::zero()->toScale(2);
            foreach ($amounts as $amount) {
                $gross = $gross->plus($amount);
            }
            if ($gross->isGreaterThan('9999999999999.99')) {
                throw ValidationException::withMessages(['transaction_ids' => 'Select fewer transactions; this batch exceeds the supported amount.']);
            }
            $ref = 'OSPM-SET-'.Str::ulid();
            $settlement = Settlement::create(['settlement_reference' => $ref, 'provider' => 'demo', 'provider_settlement_reference' => 'DEMO-'.$ref, 'period_start' => $period['period_start'], 'period_end' => $period['period_end'], 'gross_amount' => (string) $gross, 'provider_fees' => '0.00', 'net_amount' => (string) $gross, 'currency' => 'NGN', 'status' => 'settled', 'settled_at' => now(), 'metadata' => ['demo' => true, 'scenario' => $data['scenario'], 'created_by' => $actor->id, 'fee_policy' => 'Synthetic zero fees; no government account configured.']]);
            foreach ($entries as $index => $entry) {
                $settlement->items()->create(['financial_transaction_id' => $entry->id, 'amount' => $amounts[$index], 'status' => 'settled', 'created_at' => now()]);
            }
            app(FinancialAuditService::class)->recordEntity($actor, $settlement, 'settlement.created', $ref, $settlement->gross_amount, 'NGN', ['demo' => true, 'scenario' => $data['scenario'], 'item_count' => $entries->count()]);
            activity('settlements')->causedBy($actor)->performedOn($settlement)->log('Demo settlement created');

            return $settlement;
        });
    }
}
