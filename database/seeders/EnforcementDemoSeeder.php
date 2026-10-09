<?php

namespace Database\Seeders;

use App\Domains\Enforcement\Actions\RecordInspectionAction;
use App\Domains\Enforcement\Services\FieldLookupService;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EnforcementDemoSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(config('ospm.demo_mode') && ! app()->environment('production'), 403);
        $key = hash('sha256', 'ospm-enforcement-demo-v1');
        if (DB::table('idempotency_keys')->where('idempotency_key', $key)->exists()) {
            return;
        }
        $officer = User::where('email', 'enforcement@demo.local')->firstOrFail();
        $context = app(FieldLookupService::class)->contexts($officer)->first();
        if (! $context) {
            return;
        }
        app(RecordInspectionAction::class)->execute($officer, ['context' => $context['context'], 'inspection_type' => 'vehicle_check', 'result' => 'requires_review', 'notes' => 'Synthetic inspection for presentation. No incident or penalty recorded.', 'idempotency_key' => $key]);
    }
}
