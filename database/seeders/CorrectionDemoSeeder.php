<?php

namespace Database\Seeders;

use App\Domains\Assignments\Models\DriverAssignment;
use App\Domains\Finance\Actions\ApproveRefundAction;
use App\Domains\Finance\Actions\ProcessRefundAction;
use App\Domains\Finance\Actions\RequestAdjustmentAction;
use App\Domains\Finance\Actions\RequestRefundAction;
use App\Domains\Finance\Models\FinancialTransaction;
use App\Domains\Identity\Models\User;
use App\Domains\Payments\Actions\InitiatePaymentAction;
use App\Domains\Revenue\Models\RevenueHead;
use App\Domains\Ticketing\Actions\IssueTicketAction;
use App\Domains\Ticketing\DTOs\IssueTicketData;
use App\Domains\Ticketing\Models\Ticket;
use App\Domains\Ticketing\Services\TicketIssuanceService;
use App\Support\Payments\PaymentGatewayManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CorrectionDemoSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app(PaymentGatewayManager::class)->demoEnabled(), 403);
        $key = hash('sha256', 'ospm-correction-demo-v1-adjustment');
        if (DB::table('idempotency_keys')->where('idempotency_key', $key)->exists()) {
            return;
        }
        $requester = User::where('email', 'superadmin@demo.local')->firstOrFail();
        $approver = User::where('email', 'finance@demo.local')->firstOrFail();
        $a = DriverAssignment::where('status', 'active')->whereHas('driver', fn ($q) => $q->where('driver_number', 'DEMO-DRV-OSG-1'))->first();
        $head = RevenueHead::where('code', 'DEMO-DPT')->where('status', 'active')->first();
        if (! $a || ! $head) {
            return;
        }
        DB::transaction(function () use ($key, $requester, $approver, $a, $head) {
            $ticketKey = substr(hash('sha256', 'ospm-correction-demo-v1-ticket'), 0, 32);
            $t = Ticket::where('context_snapshot->issuance_request_key', $ticketKey)->first();
            if (! $t) {
                $review = app(TicketIssuanceService::class)->review($requester, $a->id, $head->id, requestKey: $ticketKey);
                $t = app(IssueTicketAction::class)->execute($requester, new IssueTicketData($a->id, $head->id, $review['confirmation'], $ticketKey));
            }
            $p = app(InitiatePaymentAction::class)->execute($requester, $t, 'successful', hash('sha256', 'ospm-correction-demo-v1-payment'));
            $r = app(RequestRefundAction::class)->execute($requester, $p, ['amount' => '0.10', 'reason' => 'Synthetic partial refund demonstration.', 'idempotency_key' => hash('sha256', 'ospm-correction-demo-v1-refund')]);
            app(ApproveRefundAction::class)->execute($approver, $r, 'approved', 'Synthetic independent refund approval.', hash('sha256', 'ospm-correction-demo-v1-review'));
            app(ProcessRefundAction::class)->execute($approver, $r, 'successful', 'Synthetic demo processing, no real funds.', hash('sha256', 'ospm-correction-demo-v1-process'));
            $entry = FinancialTransaction::where('payment_id', $p->id)->where('transaction_type', 'payment')->firstOrFail();
            app(RequestAdjustmentAction::class)->execute($requester, $entry, ['amount' => '0.10', 'adjustment_type' => 'debit', 'reason' => 'Synthetic adjustment awaiting independent review.', 'idempotency_key' => $key]);
        }, 3);
    }
}
