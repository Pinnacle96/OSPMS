<?php

namespace Database\Seeders;

use App\Domains\Complaints\Actions\CreateComplaintAction;
use App\Domains\Complaints\Actions\ManageComplaintAction;
use App\Domains\Complaints\Models\Complaint;
use App\Domains\Identity\Models\User;
use App\Domains\Incidents\Services\OperationalScope;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ComplaintDemoSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(config('ospm.demo_mode') && ! app()->environment('production'), 403);
        $u = User::role('Help Desk Officer')->where('status', 'active')->first();
        if (! $u) {
            return;
        }
        $p = app(OperationalScope::class)->parks($u)->whereNull('deleted_at')->where('status', 'active')->orderBy('id')->first();
        if (! $p) {
            return;
        }
        $key = hash('sha256', 'ospm-complaint-demo-v1');
        $old = DB::table('idempotency_keys')->where('idempotency_key', $key)->first();
        $c = $old ? Complaint::findOrFail(json_decode($old->response_payload, true)['id']) : app(CreateComplaintAction::class)->execute($u, ['complainant_name' => 'Synthetic Presentation Resident', 'category' => 'Service enquiry', 'description' => 'Synthetic service complaint for Help Desk assignment and resolution demonstration.', 'park' => $p->public_id, 'idempotency_key' => $key]);
        foreach ([['receive', ['action' => 'status', 'status' => 'received']], ['assign', ['action' => 'assign', 'assignee' => $u->public_id]], ['note', ['action' => 'note', 'note' => 'Synthetic internal follow-up note retained with staff identity.', 'is_internal' => true]]] as [$step,$data]) {
            $key = hash('sha256', 'ospm-complaint-demo-v1:'.$step);
            if (DB::table('idempotency_keys')->where('idempotency_key', $key)->exists()) {
                continue;
            }
            $c = $c->fresh();
            if (($step === 'receive' && $c->status->value !== 'submitted') || ($step === 'assign' && $c->status->value !== 'received') || ($step === 'note' && $c->status->value === 'closed')) {
                continue;
            }
            app(ManageComplaintAction::class)->execute($u, $c, [...$data, 'reason' => 'Synthetic Help Desk routing decision.', 'expected_status' => $c->status->value, 'expected_assignee' => $c->assignee?->public_id, 'idempotency_key' => $key]);
        }
    }
}
