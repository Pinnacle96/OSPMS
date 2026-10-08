<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Models\Refund;
use App\Domains\Finance\Services\FinancialConfirmationService;
use App\Domains\Finance\Services\FinancialIntegrityService;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class ApproveRefundAction
{
    public function execute(User $actor, Refund $refund, string $decision, string $reason, string $key): Refund
    {
        Gate::forUser($actor)->authorize('approve', $refund);
        Validator::make(['decision' => $decision], ['decision' => 'required|in:approved,rejected'])->validate();
        $s = app(FinancialIntegrityService::class);
        $reason = $s->reason($reason);

        return app(FinancialConfirmationService::class)->execute($actor, $key, 'refund.review', ['refund' => $refund->public_id, 'decision' => $decision, 'reason' => $reason], Refund::class, function () use ($actor, $refund, $decision, $reason, $s) {
            $p = $s->lock($refund->payment);
            $r = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('approve', $r);
            if ($r->status->value !== 'requested') {
                $s->fail('Only a requested refund can be approved or rejected.');
            }
            if ($decision === 'approved') {
                if ($p->status->value !== 'successful') {
                    $s->fail('Payment is no longer refundable.');
                }
                $s->fits($r->amount, $s->remaining($p, $r->id)['refundable']);
            }
            $r->update(['status' => $decision, $decision === 'approved' ? 'approved_by' : 'rejected_by' => $actor->id]);
            $s->audit($actor, $r, 'refund.'.$decision, $reason, ['previous_status' => 'requested']);

            return $r;
        });
    }
}
