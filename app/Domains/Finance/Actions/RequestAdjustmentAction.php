<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Models\FinancialAdjustment;
use App\Domains\Finance\Models\FinancialTransaction;
use App\Domains\Finance\Services\FinancialConfirmationService;
use App\Domains\Finance\Services\FinancialIntegrityService;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RequestAdjustmentAction
{
    public function execute(User $actor, FinancialTransaction $original, array $input): FinancialAdjustment
    {
        Gate::forUser($actor)->authorize('request', [FinancialAdjustment::class, $original]);
        $s = app(FinancialIntegrityService::class);
        $data = $s->input($input);
        $type = Validator::make($input, ['adjustment_type' => 'required|in:credit,debit'])->validate()['adjustment_type'];

        return app(FinancialConfirmationService::class)->execute($actor, $data['idempotency_key'], 'adjustment.request', ['original' => $original->public_id, 'type' => $type, 'amount' => $data['amount'], 'reason' => $data['reason']], FinancialAdjustment::class, function () use ($actor, $original, $s, $data, $type) {
            if (! $original->payment || ! $original->ticket || $original->payment->ticket_id !== $original->ticket_id) {
                $s->fail('A complete payment and ticket lineage is required.');
            }
            $p = $s->lock($original->payment);
            $t = FinancialTransaction::whereKey($original->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('request', [FinancialAdjustment::class, $t]);
            if ($t->currency !== $p->currency) {
                $s->fail('Source currency does not match the payment.');
            }
            if ($type === 'debit') {
                $s->fits($data['amount'], $s->remaining($p)['balance']);
            }
            $a = FinancialAdjustment::create(['adjustment_reference' => 'OSPM-ADJ-'.Str::ulid(), 'original_transaction_id' => $t->id, 'adjustment_type' => $type, 'amount' => $data['amount'], 'currency' => $t->currency, 'reason' => $data['reason'], 'status' => 'requested', 'requested_by' => $actor->id, 'requested_at' => now()]);
            $s->audit($actor, $a, 'adjustment.requested', $data['reason'], ['original_transaction' => $t->transaction_reference, 'direction' => $type]);

            return $a;
        });
    }
}
