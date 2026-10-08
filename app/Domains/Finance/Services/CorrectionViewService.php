<?php

namespace App\Domains\Finance\Services;

use App\Domains\Audit\Models\FinancialAuditLog;
use App\Domains\Finance\Models\Refund;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;

class CorrectionViewService
{
    public function record(Model $r): array
    {
        $refund = $r instanceof Refund;

        return [...$r->only(['public_id', 'amount', 'currency', 'reason', 'requested_at', ...($refund ? ['refund_reference', 'processed_at', 'provider_reference'] : ['adjustment_reference', 'approved_at'])]), 'reference' => $refund ? $r->refund_reference : $r->adjustment_reference, 'status' => $r->status->value, 'direction' => $refund ? 'debit' : $r->adjustment_type->value];
    }

    public function history(Model $r)
    {
        return FinancialAuditLog::where('entity_type', $r::class)->where('entity_id', $r->id)->orderBy('id')->paginate(20)->withQueryString()->through(fn ($a) => ['id' => $a->id, 'event' => $a->event_type, 'actor' => User::find($a->actor_user_id)?->name ?? 'Retained actor', 'reason' => $a->payload['reason'] ?? null, 'occurred_at' => $a->occurred_at]);
    }
}
