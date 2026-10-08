<?php

namespace App\Domains\Reconciliation\Actions;

use App\Domains\Audit\Services\FinancialAuditService;
use App\Domains\Finance\Services\FinancialConfirmationService;
use App\Domains\Identity\Models\User;
use App\Domains\Reconciliation\Models\ReconciliationItem;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ResolveReconciliationExceptionAction
{
    public function execute(User $actor, ReconciliationItem $item, array $input): ReconciliationItem
    {
        // Replay still requires current authorization, but may return the already-resolved item.
        Gate::forUser($actor)->authorize('view', $item);
        abort_unless($actor->can('resolve_reconciliation_exception') && $actor->can('access_statewide'), 403);
        $data = Validator::make(['note' => is_string($input['note'] ?? null) ? trim($input['note']) : ($input['note'] ?? ''), 'status' => $input['status'] ?? null, 'idempotency_key' => $input['idempotency_key'] ?? ''], ['note' => 'required|string|min:10|max:2000', 'status' => 'required|in:under_review,reconciled', 'idempotency_key' => 'required|string'])->validate();

        return app(FinancialConfirmationService::class)->execute($actor, $data['idempotency_key'], 'reconciliation.resolve', ['item' => $item->id, 'note' => $data['note'], 'status' => $data['status']], ReconciliationItem::class, function () use ($actor, $item, $data) {
            $item = ReconciliationItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            if (! in_array($item->status->value, ['exception', 'under_review'])) {
                throw ValidationException::withMessages(['status' => 'This finding is already matched or resolved.']);
            }
            $before = $item->status->value;
            $item->update(['status' => $data['status'], 'resolution_note' => $data['note'], 'resolved_by' => $actor->id, 'resolved_at' => now()]);
            app(FinancialAuditService::class)->recordEntity($actor, $item, 'reconciliation.exception_reviewed', $item->run->reconciliation_reference, $item->difference_amount, 'NGN', ['from' => $before, 'to' => $data['status'], 'reason' => $data['note'], 'exception_type' => $item->exception_type]);
            activity('reconciliation')->causedBy($actor)->performedOn($item)->withProperties(['from' => $before, 'to' => $data['status'], 'reason' => $data['note']])->log('Reconciliation exception reviewed');

            return $item;
        });
    }
}
