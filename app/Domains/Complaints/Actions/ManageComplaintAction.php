<?php

namespace App\Domains\Complaints\Actions;

use App\Domains\Complaints\Models\Complaint;
use App\Domains\Complaints\Models\ComplaintNote;
use App\Domains\Complaints\Services\ComplaintScope;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Services\RecordNotificationService;
use App\Http\Requests\Complaints\ManageComplaintRequest;
use App\Notifications\ComplaintAssignedNotification;
use App\Support\ConfirmedActionService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManageComplaintAction
{
    public const TRANSITIONS = ['submitted' => ['received'], 'received' => [], 'assigned' => ['under_review'], 'under_review' => ['resolved'], 'resolved' => ['closed'], 'closed' => []];

    public function execute(User $u, Complaint $c, array $input): Complaint
    {
        Gate::forUser($u)->authorize('manage', $c);
        $d = validator($input, ManageComplaintRequest::inputRules())->validate();
        foreach (['reason', 'resolution', 'note'] as $k) {
            if (isset($d[$k])) {
                $d[$k] = trim($d[$k]);
                if (mb_strlen($d[$k]) < ($k === 'note' ? 2 : 10)) {
                    throw ValidationException::withMessages([$k => 'Enter a meaningful explanation.']);
                }
            }
        }
        $payload = [...$d, 'complaint' => $c->public_id];
        unset($payload['idempotency_key']);

        return app(ConfirmedActionService::class)->execute($u, $d['idempotency_key'], 'manage_complaint', $payload, Complaint::class, function () use ($u, $c, $d) {
            $c = Complaint::whereKey($c->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($u)->authorize('manage', $c);
            if ($c->status->value !== $d['expected_status'] || ($c->assignee?->public_id) !== ($d['expected_assignee'] ?? null)) {
                throw ValidationException::withMessages(['expected_status' => 'This complaint changed. Refresh before making another decision.']);
            }
            $old = $c->status->value;
            if ($d['action'] === 'note') {
                if ($old === 'closed') {
                    throw ValidationException::withMessages(['note' => 'Closed complaint history cannot receive new notes.']);
                }
                $note = ComplaintNote::create(['complaint_id' => $c->id, 'user_id' => $u->id, 'note' => $d['note'], 'is_internal' => $d['is_internal'] ?? true, 'created_at' => now()]);
                activity('complaints')->causedBy($u)->performedOn($c)->withProperties(['note_id' => $note->id, 'is_internal' => $note->is_internal])->log('complaint_note_added');

                return $c;
            }
            if ($d['action'] === 'assign') {
                if (! in_array($old, ['received', 'assigned', 'under_review'])) {
                    throw ValidationException::withMessages(['assignee' => 'Receive this complaint before assignment. Resolved and closed complaints cannot be reassigned.']);
                }
                $target = User::where('public_id', $d['assignee'])->lockForUpdate()->first();
                if (! $target || ! app(ComplaintScope::class)->canAssign($target, $c)) {
                    throw ValidationException::withMessages(['assignee' => 'Choose an active complaint manager with current access to this park.']);
                }
                $from = $c->assignee?->public_id;
                $c->update(['assigned_to' => $target->id, 'status' => 'assigned']);
                $audit = activity('complaints')->causedBy($u)->performedOn($c)->withProperties(['from' => $old, 'to' => 'assigned', 'previous_assignee' => $from, 'assignee' => $target->public_id, 'reason' => $d['reason']])->log('complaint_assigned');
                app(RecordNotificationService::class)->send($target, $c, 'complaint', 'assignment:'.$audit->id, $c->complaint_reference, 'Complaint assigned to you', ComplaintAssignedNotification::class);

                return $c;
            }
            if (! in_array($d['status'], self::TRANSITIONS[$old])) {
                throw ValidationException::withMessages(['status' => 'This transition is not available.']);
            }
            if ($d['status'] === 'under_review' && (! $c->assignee || ! app(ComplaintScope::class)->canAssign($c->assignee, $c))) {
                throw ValidationException::withMessages(['status' => 'Reassign to an active manager with current scope before review.']);
            }
            $changes = ['status' => $d['status']];
            if ($d['status'] === 'resolved') {
                $changes += ['resolution' => $d['resolution'], 'resolved_at' => now()];
            }$c->update($changes);
            activity('complaints')->causedBy($u)->performedOn($c)->withProperties(['from' => $old, 'to' => $d['status'], 'reason' => $d['reason']])->log('complaint_status_changed');

            return $c;
        });
    }
}
