<?php

namespace Database\Seeders;

use App\Domains\Assignments\Models\DriverAssignment;
use App\Domains\Identity\Models\User;
use App\Domains\Revenue\Models\RevenueHead;
use App\Domains\Ticketing\Actions\IssueTicketAction;
use App\Domains\Ticketing\DTOs\IssueTicketData;
use App\Domains\Ticketing\Models\Ticket;
use App\Domains\Ticketing\Services\TicketIssuanceService;
use Illuminate\Database\Seeder;

class TicketDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('ospm.demo_mode') || app()->environment('production')) {
            throw new \RuntimeException('Synthetic ticket data requires non-production demo mode.');
        }
        $assignment = DriverAssignment::where('status', 'active')->whereHas('driver', fn ($q) => $q->where('driver_number', 'DEMO-DRV-OSG-1'))->first();
        $head = RevenueHead::where('code', 'DEMO-DPT')->where('status', 'active')->first();
        if (! $assignment || ! $head || Ticket::where('driver_id', $assignment->driver_id)->where('revenue_head_id', $head->id)->exists()) {
            return;
        }
        $actor = User::where('email', 'superadmin@demo.local')->firstOrFail();
        // Use normal issuance, preserving all existing tickets and cancellation history on repeats.
        $review = app(TicketIssuanceService::class)->review($actor, $assignment->id, $head->id);
        app(IssueTicketAction::class)->execute($actor, new IssueTicketData($assignment->id, $head->id, $review['confirmation'], $review['terms']['request_key']));
    }
}
