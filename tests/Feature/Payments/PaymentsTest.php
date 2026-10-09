<?php

namespace Tests\Feature\Payments;

use App\Domains\Audit\Models\FinancialAuditLog;
use App\Domains\Audit\Services\FinancialAuditService;
use App\Domains\Finance\Models\FinancialTransaction;
use App\Domains\Geography\Models\Lga;
use App\Domains\Operators\Models\Operator;
use App\Domains\Parks\Models\Park;
use App\Domains\Payments\Actions\GenerateReceiptAction;
use App\Domains\Payments\Actions\InitiatePaymentAction;
use App\Domains\Payments\Actions\RecordSuccessfulPaymentAction;
use App\Domains\Payments\Actions\ResolveDemoPaymentAction;
use App\Domains\Payments\Actions\ReversePaymentAction;
use App\Domains\Payments\Enums\PaymentStatus;
use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\Models\Receipt;
use App\Domains\Payments\Services\ReceiptVerificationService;
use App\Domains\Revenue\Models\FeeConfiguration;
use App\Domains\Revenue\Models\RevenueHead;
use App\Domains\Ticketing\Models\Ticket;
use App\Notifications\PaymentSuccessfulNotification;
use App\Support\Payments\DTOs\PaymentVerificationResult;
use App\Support\Payments\Gateways\DemoPaymentGateway;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PaymentDemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\FoundationTestCase;

class PaymentsTest extends FoundationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ospm.demo_mode' => true, 'ospm.payment_mode' => 'demo', 'ospm.payment_provider' => 'demo']);
    }

    private function fixture(string $tag = 'A'): array
    {
        $actor = $this->userWithRole('Collection Agent');
        $lga = Lga::create(['code' => $tag, 'name' => 'Synthetic LGA '.$tag, 'status' => 'active']);
        $park = Park::create(['lga_id' => $lga->id, 'park_code' => $tag, 'name' => 'Synthetic Park '.$tag, 'address' => 'PRIVATE-ADDRESS', 'status' => 'active']);
        $actor->parks()->attach($park, ['access_level' => 'manage', 'created_at' => now()]);
        $operator = Operator::create(['operator_number' => $tag, 'name' => 'Private Operator '.$tag, 'phone' => 'PRIVATE-PHONE', 'status' => 'approved']);
        $operator->parks()->attach($park, ['status' => 'active', 'created_at' => now()]);
        $head = RevenueHead::create(['code' => $tag, 'name' => 'Synthetic daily fee', 'frequency' => 'daily', 'status' => 'active']);
        $fee = FeeConfiguration::create(['revenue_head_id' => $head->id, 'amount' => '500.10', 'currency' => 'NGN', 'effective_from' => now()->subDay(), 'status' => 'active', 'priority' => 0]);
        $ticket = Ticket::create(['ticket_reference' => 'TEST-'.$tag, 'revenue_head_id' => $head->id, 'fee_configuration_id' => $fee->id, 'lga_id' => $lga->id, 'park_id' => $park->id, 'operator_id' => $operator->id, 'fee_code_snapshot' => $tag, 'fee_name_snapshot' => 'Original fee', 'amount' => '500.10', 'currency' => 'NGN', 'issued_by' => $actor->id, 'issued_at' => now(), 'ticket_status' => 'pending', 'payment_status' => 'unpaid', 'verification_token' => bin2hex(random_bytes(32)), 'context_snapshot' => ['assignment_id' => 1, 'lga' => ['code' => $tag, 'name' => $lga->name], 'park' => ['code' => $tag, 'name' => $park->name], 'operator' => ['name' => 'PRIVATE-OPERATOR', 'reference' => $tag], 'driver' => ['name' => 'PRIVATE-DRIVER', 'reference' => $tag], 'vehicle' => ['registration' => 'SYN-'.$tag, 'type' => 'bus'], 'route' => null]]);

        return compact('actor', 'ticket', 'lga', 'park', 'operator', 'head', 'fee');
    }

    private function pay(array $n, string $scenario = 'successful', ?string $key = null): Payment
    {
        return app(InitiatePaymentAction::class)->execute($n['actor'], $n['ticket'], $scenario, $key ?? bin2hex(random_bytes(32)));
    }

    public function test_success_is_atomic_and_request_and_success_replay_create_one_credit_and_receipt(): void
    {
        $n = $this->fixture();
        $key = bin2hex(random_bytes(32));
        $p = $this->pay($n, 'successful', $key);
        $this->assertSame('paid', $n['ticket']->fresh()->ticket_status->value);
        $this->assertSame('paid', $n['ticket']->fresh()->payment_status->value);
        $this->assertSame('500.10', $p->amount);
        $this->assertNotNull($p->paid_at);
        $this->assertSame($p->id, $this->pay($n, 'successful', $key)->id);
        $this->assertSame($p->id, app(RecordSuccessfulPaymentAction::class)->execute($n['actor'], $p)->id);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('receipts', 1);
        $this->assertDatabaseCount('financial_transactions', 1);
        $this->assertDatabaseCount('financial_audit_logs', 2);
        $this->assertSame('credit', FinancialTransaction::first()->direction);
        $notice = $n['actor']->notifications()->sole();
        $this->assertSame(PaymentSuccessfulNotification::class, $notice->type);
        $this->assertSame($p->public_id, $notice->data['subject']);
    }

    public function test_failed_and_pending_attempts_have_no_receipt_or_ledger_and_failed_can_retry(): void
    {
        $n = $this->fixture();
        $failed = $this->pay($n, 'failed');
        $this->assertSame('failed', $failed->status->value);
        $this->assertNotNull($failed->failed_at);
        $this->assertSame('failed', $n['ticket']->fresh()->payment_status->value);
        $pending = $this->pay($n, 'pending');
        $this->assertSame('pending', $pending->status->value);
        $this->assertNull($pending->paid_at);
        $this->assertDatabaseCount('receipts', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertSame(0, $n['actor']->notifications()->count());
        $this->expectException(ValidationException::class);
        $this->pay($n);
    }

    public function test_pending_resolution_is_idempotent_and_only_success_creates_receipt(): void
    {
        $n = $this->fixture();
        $p = $this->pay($n, 'pending');
        $key = bin2hex(random_bytes(32));
        $action = app(ResolveDemoPaymentAction::class);
        $action->execute($n['actor'], $p, 'successful', $key);
        $action->execute($n['actor'], $p, 'successful', $key);
        $this->assertSame('successful', $p->fresh()->status->value);
        $this->assertDatabaseCount('receipts', 1);
        $this->assertDatabaseCount('financial_transactions', 1);
    }

    public function test_pending_failure_remains_receipt_free(): void
    {
        $n = $this->fixture();
        $p = $this->pay($n, 'pending');
        app(ResolveDemoPaymentAction::class)->execute($n['actor'], $p, 'failed', bin2hex(random_bytes(32)));
        $this->assertSame('failed', $p->fresh()->status->value);
        $this->assertDatabaseCount('receipts', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_changed_payload_cannot_reuse_idempotency_key(): void
    {
        $n = $this->fixture();
        $key = bin2hex(random_bytes(32));
        $this->pay($n, 'failed', $key);
        $this->expectException(ValidationException::class);
        $this->pay($n, 'successful', $key);
    }

    public function test_forged_client_money_status_provider_and_actor_are_ignored(): void
    {
        $n = $this->fixture();
        $this->actingAs($n['actor'])->post('/tickets/'.$n['ticket']->public_id.'/pay', ['scenario' => 'successful', 'idempotency_key' => bin2hex(random_bytes(32)), 'amount' => '0.01', 'currency' => 'USD', 'status' => 'successful', 'created_by' => 999, 'provider' => 'bank'])->assertSessionHasNoErrors();
        $p = Payment::firstOrFail();
        $this->assertSame('500.10', $p->amount);
        $this->assertSame('NGN', $p->currency);
        $this->assertSame('demo', $p->provider);
        $this->assertSame($n['actor']->id, $p->created_by);
    }

    public function test_paid_cancelled_reversed_expired_and_zero_obligations_reject_new_payment(): void
    {
        $n = $this->fixture();
        $this->actingAs($n['actor']);
        foreach ([['ticket_status' => 'paid', 'payment_status' => 'paid'], ['ticket_status' => 'cancelled', 'payment_status' => 'unpaid'], ['ticket_status' => 'reversed', 'payment_status' => 'reversed'], ['ticket_status' => 'expired', 'payment_status' => 'unpaid'], ['ticket_status' => 'pending', 'payment_status' => 'unpaid', 'expires_at' => now()->subMinute()]] as $change) {
            DB::table('tickets')->where('id', $n['ticket']->id)->update($change);
            $this->post('/tickets/'.$n['ticket']->public_id.'/pay', ['scenario' => 'successful', 'idempotency_key' => bin2hex(random_bytes(32))])->assertSessionHasErrors('scenario');
        }
        DB::table('tickets')->where('id', $n['ticket']->id)->update(['ticket_status' => 'pending', 'expires_at' => null, 'amount' => '0.00']);
        $this->post('/tickets/'.$n['ticket']->public_id.'/pay', ['scenario' => 'successful', 'idempotency_key' => bin2hex(random_bytes(32))])->assertSessionHasErrors('scenario');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_receipt_failure_rolls_back_payment_ticket_credit_audit_and_idempotency(): void
    {
        $n = $this->fixture();
        $mock = \Mockery::mock(GenerateReceiptAction::class);
        $mock->shouldReceive('execute')->andThrow(new \RuntimeException('Synthetic receipt failure'));
        $this->app->instance(GenerateReceiptAction::class, $mock);
        try {
            $this->pay($n);
            $this->fail('Expected receipt failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic receipt failure', $e->getMessage());
        }
        foreach (['payments', 'receipts', 'financial_transactions', 'financial_audit_logs', 'idempotency_keys'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }$this->assertSame('unpaid', $n['ticket']->fresh()->payment_status->value);
    }

    public function test_mismatched_gateway_confirmation_cannot_create_financial_success(): void
    {
        $n = $this->fixture();
        $p = $this->pay($n, 'pending');
        $mock = \Mockery::mock(DemoPaymentGateway::class);
        $mock->shouldReceive('verify')->andReturn(new PaymentVerificationResult($p->payment_reference, $p->provider_reference, '0.01', 'NGN', PaymentStatus::Successful));
        $this->app->instance(DemoPaymentGateway::class, $mock);
        try {
            app(RecordSuccessfulPaymentAction::class)->execute($n['actor'], $p);
            $this->fail('Accepted mismatch');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('scenario', $e->errors());
        }$this->assertDatabaseCount('receipts', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertSame('pending', $p->fresh()->status->value);
    }

    public function test_supervisor_reversal_adds_one_debit_retains_original_credit_and_invalidates_verifications(): void
    {
        $n = $this->fixture();
        $p = $this->pay($n);
        $credit = FinancialTransaction::first();
        $receipt = Receipt::first();
        $finance = $this->userWithRole('Finance Administrator');
        $key = bin2hex(random_bytes(32));
        $action = app(ReversePaymentAction::class);
        $action->execute($finance, $p, 'Synthetic duplicate settlement', $key);
        $action->execute($finance, $p, 'Synthetic duplicate settlement', $key);
        $this->assertDatabaseCount('financial_transactions', 2);
        $this->assertDatabaseCount('receipts', 1);
        $this->assertSame('credit', $credit->fresh()->direction);
        $debit = FinancialTransaction::where('direction', 'debit')->firstOrFail();
        $this->assertSame($credit->id, $debit->parent_transaction_id);
        $this->assertSame($credit->amount, $debit->amount);
        $this->assertSame('reversed', $p->fresh()->status->value);
        $this->assertFalse(app(ReceiptVerificationService::class)->safe($receipt)['valid']);
        $this->get('/verify/ticket/'.$n['ticket']->verification_token)->assertInertia(fn (Assert $a) => $a->where('verification.valid', false));
    }

    public function test_collectors_and_auditors_cannot_reverse_or_mutate_ledger(): void
    {
        $n = $this->fixture();
        $p = $this->pay($n);
        foreach ([$n['actor'], $this->userWithRole('Auditor')] as $actor) {
            $this->actingAs($actor)->post('/payments/'.$p->public_id.'/reverse', ['reason' => 'Forbidden', 'idempotency_key' => bin2hex(random_bytes(32))])->assertForbidden();
            $this->patch('/finance/ledger/'.FinancialTransaction::first()->public_id, ['amount' => '0.01'])->assertStatus(405);
        }$this->assertDatabaseCount('financial_transactions', 1);
    }

    public function test_finance_models_prohibit_deletion_and_immutable_terms_updates(): void
    {
        $n = $this->fixture();
        $p = $this->pay($n);
        foreach ([[$p, ['amount' => '0.01']], [Receipt::first(), ['receipt_number' => 'FORGED']], [FinancialTransaction::first(), ['amount' => '0.01']], [FinancialAuditLog::first(), ['payload' => []]]] as [$record,$changes]) {
            foreach (['update', 'delete'] as $method) {
                try {
                    $method === 'update' ? $record->update($changes) : $record->delete();
                    $this->fail('Allowed financial mutation');
                } catch (\LogicException $e) {
                    $this->assertNotEmpty($e->getMessage());
                }
            }
        }
    }

    public function test_financial_hash_chain_is_complete_and_detects_tampering(): void
    {
        $n = $this->fixture();
        $this->pay($n);
        $previous = null;
        $service = app(FinancialAuditService::class);
        foreach (FinancialAuditLog::orderBy('id')->get() as $row) {
            $this->assertSame($previous, $row->previous_hash);
            $data = $row->getAttributes();
            unset($data['id'],$data['entry_hash']);
            $data['payload'] = $row->payload;
            $data['amount'] = $row->amount;
            $this->assertSame($row->entry_hash, $service->hash($data));
            $data['amount'] = '0.01';
            $this->assertNotSame($row->entry_hash, $service->hash($data));
            $previous = $row->entry_hash;
        }
    }

    public function test_public_receipt_allowlist_hides_identity_tokens_account_and_audit_data(): void
    {
        $n = $this->fixture();
        $this->pay($n);
        $r = Receipt::first();
        $safe = app(ReceiptVerificationService::class)->verify($r->verification_token);
        $this->assertTrue($safe['valid']);
        foreach (['PRIVATE-', $r->verification_token, $r->public_id] as $private) {
            $this->assertStringNotContainsString($private, json_encode($safe));
        }
        $this->actingAs($this->userWithRole('Super Administrator'))->get('/verify/receipt/'.$r->verification_token)->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')->assertInertia(fn (Assert $a) => $a->component('Public/ReceiptVerification')->where('verification.valid', true)->where('auth.user', null)->has('navigation', 0)->missing('verification.context')->missing('verification.verification_token'));
        foreach (['malformed', str_repeat('a', 64)] as $token) {
            $this->get('/verify/receipt/'.$token)->assertNotFound()->assertInertia(fn (Assert $a) => $a->where('verification', null));
        }
    }

    public function test_receipt_proves_payment_even_when_ticket_has_expired(): void
    {
        $n = $this->fixture();
        $this->pay($n);
        DB::table('tickets')->where('id', $n['ticket']->id)->update(['expires_at' => now()->subMinute()]);
        $safe = app(ReceiptVerificationService::class)->safe(Receipt::first());
        $this->assertTrue($safe['valid']);
        $this->assertSame('expired', $safe['ticket_status']);
    }

    public function test_geography_operator_and_manage_scopes_protect_payments_receipts_pdf_and_ledger(): void
    {
        $a = $this->fixture('A');
        $b = $this->fixture('B');
        $pa = $this->pay($a);
        $pb = $this->pay($b);
        $rb = $pb->receipt;
        $this->actingAs($a['actor'])->get('/payments')->assertInertia(fn (Assert $a) => $a->has('records.data', 1));
        foreach (['/payments/'.$pb->public_id, '/receipts/'.$rb->public_id, '/receipts/'.$rb->public_id.'/pdf', '/tickets/'.$b['ticket']->public_id.'/pay'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $viewer = $this->userWithRole('Collection Agent');
        $viewer->parks()->attach($a['park'], ['access_level' => 'view', 'created_at' => now()]);
        $this->actingAs($viewer)->post('/tickets/'.$a['ticket']->public_id.'/pay', ['scenario' => 'successful', 'idempotency_key' => bin2hex(random_bytes(32))])->assertForbidden();
        DB::table('tickets')->where('id', $b['ticket']->id)->update(['park_id' => $a['park']->id, 'lga_id' => $a['lga']->id]);
        $operator = $this->userWithRole('Transport Operator');
        $operator->operators()->attach($a['operator'], ['access_level' => 'view', 'created_at' => now()]);
        $this->actingAs($operator)->get('/payments')->assertInertia(fn (Assert $a) => $a->has('records.data', 1));
        $this->get('/receipts/'.$rb->public_id)->assertForbidden();
        $local = $this->userWithRole('LGA Administrator');
        $local->lgas()->attach($b['lga'], ['access_level' => 'view', 'created_at' => now()]);
        $this->actingAs($local)->get('/finance/ledger')->assertInertia(fn (Assert $a) => $a->has('records.data', 0));
        $this->get('/finance/ledger/'.FinancialTransaction::where('payment_id', $pa->id)->first()->public_id)->assertForbidden();
    }

    public function test_all_payment_receipt_and_ledger_screens_and_pdf_render_real_data(): void
    {
        $n = $this->fixture();
        $this->actingAs($n['actor'])->get('/tickets/'.$n['ticket']->public_id.'/pay')->assertInertia(fn (Assert $a) => $a->component('Payments/DemoPay'));
        $p = $this->pay($n);
        $r = $p->receipt;
        foreach (['/payments' => 'Payments/Index', '/payments/'.$p->public_id => 'Payments/Show', '/receipts/'.$r->public_id => 'Receipts/Show', '/receipts/'.$r->public_id.'/print' => 'Receipts/Print'] as $url => $component) {
            $this->get($url)->assertOk()->assertInertia(fn (Assert $a) => $a->component($component));
        }
        $pdf = $this->get('/receipts/'.$r->public_id.'/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertDatabaseHas('activity_log', ['description' => 'receipt_pdf_downloaded']);
        $this->actingAs($this->userWithRole('Auditor'));
        $this->get('/finance/ledger')->assertInertia(fn (Assert $a) => $a->component('Finance/Ledger/Index')->has('records.data', 1));
        $this->get('/finance/ledger/'.FinancialTransaction::first()->public_id)->assertInertia(fn (Assert $a) => $a->component('Finance/Ledger/Show'));
    }

    public function test_demo_mode_is_required_and_production_provider_configuration_fails_closed(): void
    {
        $n = $this->fixture();
        foreach ([['ospm.demo_mode' => false], ['ospm.demo_mode' => true, 'ospm.payment_mode' => 'live'], ['ospm.payment_mode' => 'demo', 'ospm.payment_provider' => 'bank']] as $config) {
            config($config);
            $this->actingAs($n['actor'])->post('/tickets/'.$n['ticket']->public_id.'/pay', ['scenario' => 'successful', 'idempotency_key' => bin2hex(random_bytes(32))])->assertForbidden();
        }$this->assertDatabaseCount('payments', 0);
    }

    public function test_lists_search_filter_sort_and_paginate_without_tokens(): void
    {
        $n = $this->fixture();
        for ($i = 0; $i < 16; $i++) {
            $this->pay($n, 'failed');
        }$this->actingAs($n['actor'])->get('/payments?search=SYN-A&status=failed&sort=amount&order=asc')->assertInertia(fn (Assert $a) => $a->has('records.data', 15)->where('records.total', 16)->missing('records.data.0.idempotency_key')->missing('records.data.0.provider_metadata'));
        $this->get('/payments?status=successful')->assertInertia(fn (Assert $a) => $a->has('records.data', 0));
        $this->get('/payments?to=2099-12-31')->assertOk()->assertSessionHasNoErrors();
        $this->get('/payments?from=2026-10-10&to=2026-10-01')->assertSessionHasErrors('to');
    }

    public function test_idempotency_keys_cannot_cross_actors_or_tickets(): void
    {
        $a = $this->fixture('A');
        $b = $this->fixture('B');
        $key = bin2hex(random_bytes(32));
        $this->pay($a, 'failed', $key);
        try {
            $this->pay($b, 'successful', $key);
            $this->fail('Foreign confirmation reused');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('idempotency_key', $e->errors());
        }
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('receipts', 0);
        $this->assertSame('unpaid', $b['ticket']->fresh()->payment_status->value);
    }

    public function test_public_receipt_verification_is_rate_limited(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->get('/verify/receipt/not-a-token')->assertNotFound();
        }
        $this->get('/verify/receipt/not-a-token')->assertStatus(429);
    }

    public function test_production_environment_cannot_simulate_payments(): void
    {
        $n = $this->fixture();
        $this->app['env'] = 'production';
        $this->actingAs($n['actor'])->withSession(['_token' => 'synthetic-production-guard-check'])->post('/tickets/'.$n['ticket']->public_id.'/pay', ['_token' => 'synthetic-production-guard-check', 'scenario' => 'successful', 'idempotency_key' => bin2hex(random_bytes(32))])->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_ledger_filters_retain_credit_and_reversal_and_seeding_preserves_history(): void
    {
        config(['ospm.demo_password' => 'SyntheticDemoPassword42']);
        $this->seed(DatabaseSeeder::class);
        $payment = Payment::where('idempotency_key', hash('sha256', 'ospm-synthetic-payment-v1-successful'))->firstOrFail();
        $finance = $this->userWithRole('Finance Administrator');
        app(ReversePaymentAction::class)->execute($finance, $payment, 'Synthetic seeder retention check', bin2hex(random_bytes(32)));
        $count = Ticket::count();
        $this->seed(PaymentDemoSeeder::class);
        $this->assertSame($count, Ticket::count());
        $this->assertDatabaseCount('payments', 6);
        $this->assertDatabaseCount('receipts', 4);
        $this->assertDatabaseCount('financial_transactions', 6);
        $this->assertSame('reversed', $payment->fresh()->status->value);
        $this->actingAs($finance)->get('/finance/ledger?direction=debit&transaction_type=reversal&search='.$payment->payment_reference.'&sort=amount&order=asc')->assertInertia(fn (Assert $a) => $a->has('records.data', 1)->where('records.data.0.direction', 'debit'));
        $this->get('/finance/ledger?direction=credit')->assertInertia(fn (Assert $a) => $a->has('records.data', 4));
    }
}
