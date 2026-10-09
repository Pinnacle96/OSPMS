<?php

namespace Database\Seeders;

use App\Domains\Enforcement\Actions\RecordViolationAction;
use App\Domains\Enforcement\Models\Inspection;
use App\Domains\Enforcement\Services\FieldLookupService;
use App\Domains\Identity\Models\User;
use App\Domains\Incidents\Actions\CreateIncidentAction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IncidentDemoSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(config('ospm.demo_mode') && ! app()->environment('production'), 403);
        $officer = User::where('email', 'enforcement@demo.local')->firstOrFail();
        $context = app(FieldLookupService::class)->contexts($officer)->first();
        if (! $context) {
            return;
        }
        $key = hash('sha256', 'ospm-incident-demo-v1');
        if (! DB::table('idempotency_keys')->where('idempotency_key', $key)->exists()) {
            app(CreateIncidentAction::class)->execute($officer, ['park' => $context['park']['public_id'], 'context' => $context['context'], 'category' => 'safety_issue', 'description' => 'Synthetic safety observation for the presentation. Supervisor review is required; no finding of guilt is recorded.', 'occurred_at' => now()->setTimezone('Africa/Lagos')->subMinute()->format('Y-m-d\TH:i'), 'idempotency_key' => $key]);
        }
        $key = hash('sha256', 'ospm-violation-demo-v1');
        $inspection = Inspection::where('officer_user_id', $officer->id)->whereIn('result', ['requires_review', 'non_compliant'])->orderBy('id')->first();
        if ($inspection && ! DB::table('idempotency_keys')->where('idempotency_key', $key)->exists()) {
            app(RecordViolationAction::class)->execute($officer, $inspection, ['category' => 'Recorded checks require review', 'description' => 'Synthetic observation linked to the retained inspection. No statutory offence or financial penalty is asserted.', 'idempotency_key' => $key]);
        }
    }
}
