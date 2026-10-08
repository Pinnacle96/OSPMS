<?php

namespace Database\Seeders;

use App\Domains\Assignments\Models\DriverAssignment;
use App\Domains\Finance\Actions\CreateSettlementAction;
use App\Domains\Finance\Models\FinancialTransaction;
use App\Domains\Identity\Models\User;
use App\Domains\Payments\Actions\InitiatePaymentAction;
use App\Domains\Reconciliation\Actions\RunReconciliationAction;
use App\Domains\Reconciliation\Actions\StartReconciliationAction;
use App\Domains\Revenue\Models\RevenueHead;
use App\Domains\Ticketing\Actions\IssueTicketAction;
use App\Domains\Ticketing\DTOs\IssueTicketData;
use App\Domains\Ticketing\Models\Ticket;
use App\Domains\Ticketing\Services\TicketIssuanceService;
use App\Support\Payments\PaymentGatewayManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReconciliationDemoSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app(PaymentGatewayManager::class)->demoEnabled(), 403, 'Synthetic reconciliation requires non-production demo mode.');
        $runKey = hash('sha256', 'ospm-reconciliation-demo-v1-run');
        // A reset never rewrites a reviewed finding or generates another demonstration batch.
        if (DB::table('idempotency_keys')->where('idempotency_key', $runKey)->exists()) {
            return;
        }
        $actor = User::where('email', 'superadmin@demo.local')->firstOrFail();
        $assignment = DriverAssignment::where('status', 'active')->whereHas('driver', fn ($q) => $q->where('driver_number', 'DEMO-DRV-OSG-1'))->first();
        $head = RevenueHead::where('code', 'DEMO-DPT')->where('status', 'active')->first();
        if (! $assignment || ! $head) {
            return;
        }
        DB::transaction(function () use ($actor, $assignment, $head, $runKey) {
            $tickets = collect();
            foreach (['matched', 'missing_payment', 'amount_mismatch'] as $scenario) {
                $key = substr(hash('sha256', 'ospm-reconciliation-demo-v1-ticket-'.$scenario), 0, 32);
                $ticket = Ticket::where('context_snapshot->issuance_request_key', $key)->first();
                if (! $ticket) {
                    $review = app(TicketIssuanceService::class)->review($actor, $assignment->id, $head->id, requestKey: $key);
                    $ticket = app(IssueTicketAction::class)->execute($actor, new IssueTicketData($assignment->id, $head->id, $review['confirmation'], $key));
                }
                $tickets->push($ticket);
                if ($scenario === 'missing_payment') {
                    continue;
                }
                $payment = app(InitiatePaymentAction::class)->execute($actor, $ticket, 'successful', hash('sha256', 'ospm-reconciliation-demo-v1-payment-'.$scenario));
                $day = $payment->paid_at->setTimezone(config('ospm.timezone'))->toDateString();
                app(CreateSettlementAction::class)->execute($actor, ['from' => $day, 'to' => $day, 'scenario' => $scenario, 'transaction_ids' => FinancialTransaction::where('payment_id', $payment->id)->where('direction', 'credit')->pluck('id')->all(), 'idempotency_key' => hash('sha256', 'ospm-reconciliation-demo-v1-settlement-'.$scenario)]);
            }
            $run = app(StartReconciliationAction::class)->execute($actor, ['from' => $tickets->min('issued_at')->setTimezone(config('ospm.timezone'))->toDateString(), 'to' => $tickets->max('issued_at')->setTimezone(config('ospm.timezone'))->toDateString(), 'park_id' => $assignment->park_id, 'provider' => 'demo', 'idempotency_key' => $runKey]);
            app(RunReconciliationAction::class)->execute($run);
        }, 3);
    }
}
