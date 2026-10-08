<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Models\FinancialAdjustment;
use App\Domains\Finance\Models\FinancialTransaction;
use App\Domains\Finance\Services\FinancialConfirmationService;
use App\Domains\Finance\Services\FinancialIntegrityService;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class ApproveAdjustmentAction
{
    public function execute(User $actor, FinancialAdjustment $adjustment, string $decision, string $reason, string $key): FinancialAdjustment
    {
        Gate::forUser($actor)->authorize('approve', $adjustment);
        Validator::make(['decision' => $decision], ['decision' => 'required|in:approved,rejected'])->validate();
        $s = app(FinancialIntegrityService::class);
        $reason = $s->reason($reason);

        return app(FinancialConfirmationService::class)->execute($actor, $key, 'adjustment.review', ['adjustment' => $adjustment->public_id, 'decision' => $decision, 'reason' => $reason], FinancialAdjustment::class, function () use ($actor, $adjustment, $decision, $reason, $s) {
            $p = $s->lock($adjustment->originalTransaction->payment);
            $a = FinancialAdjustment::whereKey($adjustment->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('approve', $a);
            if ($a->status->value !== 'requested') {
                $s->fail('Only a requested adjustment can be reviewed.');
            }
            $t = FinancialTransaction::whereKey($a->original_transaction_id)->lockForUpdate()->firstOrFail();
            if ($decision === 'approved') {
                if ($a->adjustment_type->value === 'debit') {
                    $s->fits($a->amount, $s->remaining($p, null, $a->id)['balance']);
                }
                if ($a->adjustment_type->value === 'debit') {
                    app(CreateLedgerDebitAction::class)->execute($actor, $t, 'ADJ-'.$a->adjustment_reference, $a->amount, 'adjustment', $a->reason);
                } else {
                    app(CreateLedgerCreditAction::class)->correction($actor, $t, 'ADJ-'.$a->adjustment_reference, $a->amount, $a->reason);
                }
            }
            $a->update(['status' => $decision, 'approved_by' => $decision === 'approved' ? $actor->id : null, 'approved_at' => $decision === 'approved' ? now() : null]);
            $s->audit($actor, $a, 'adjustment.'.$decision, $reason, ['previous_status' => 'requested', 'original_transaction' => $t->transaction_reference, 'direction' => $a->adjustment_type->value]);

            return $a;
        });
    }
}
