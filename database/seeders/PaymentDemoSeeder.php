<?php

namespace Database\Seeders;

use App\Domains\Assignments\Models\DriverAssignment;
use App\Domains\Identity\Models\User;
use App\Domains\Payments\Actions\InitiatePaymentAction;
use App\Domains\Payments\Models\Payment;
use App\Domains\Revenue\Models\RevenueHead;
use App\Domains\Ticketing\Actions\IssueTicketAction;
use App\Domains\Ticketing\DTOs\IssueTicketData;
use App\Domains\Ticketing\Services\TicketIssuanceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('ospm.demo_mode') || app()->environment('production')) {
            throw new \RuntimeException('Synthetic payments require non-production demo mode.');
        }
        $actor = User::where('email', 'superadmin@demo.local')->firstOrFail();
        $assignment = DriverAssignment::where('status', 'active')->whereHas('driver', fn ($q) => $q->where('driver_number', 'DEMO-DRV-OSG-1'))->first();
        $head = RevenueHead::where('code', 'DEMO-DPT')->where('status', 'active')->first();
        if (! $assignment || ! $head) {
            return;
        }
        foreach (['successful', 'failed', 'pending'] as $scenario) {
            $key = hash('sha256', 'ospm-synthetic-payment-v1-'.$scenario);
            if (Payment::where('idempotency_key', $key)->exists()) {
                continue;
            }
            DB::transaction(function () use ($actor, $assignment, $head, $scenario, $key) {
                $review = app(TicketIssuanceService::class)->review($actor, $assignment->id, $head->id);
                $ticket = app(IssueTicketAction::class)->execute($actor, new IssueTicketData($assignment->id, $head->id, $review['confirmation'], $review['terms']['request_key']));
                app(InitiatePaymentAction::class)->execute($actor, $ticket, $scenario, $key);
            }, 3);
        }
    }
}
