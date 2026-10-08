<?php

namespace Tests\Feature\Finance;

use App\Domains\Audit\Models\FinancialAuditLog;
use App\Domains\Audit\Services\FinancialAuditService;
use App\Domains\Finance\Actions\ApproveAdjustmentAction;
use App\Domains\Finance\Actions\ApproveRefundAction;
use App\Domains\Finance\Actions\ProcessRefundAction;
use App\Domains\Finance\Actions\RequestAdjustmentAction;
use App\Domains\Finance\Actions\RequestRefundAction;
use App\Domains\Finance\Enums\RefundStatus;
use App\Domains\Finance\Models\FinancialAdjustment;
use App\Domains\Finance\Models\FinancialTransaction;
use App\Domains\Finance\Models\Refund;
use App\Domains\Finance\Services\FinancePeriod;
use App\Domains\Finance\Services\FinancialIntegrityService;
use App\Domains\Finance\Services\SettlementService;
use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Models\User;
use App\Domains\Operators\Models\Operator;
use App\Domains\Parks\Models\Park;
use App\Domains\Payments\Actions\InitiatePaymentAction;
use App\Domains\Payments\Actions\ReversePaymentAction;
use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\Services\ReceiptVerificationService;
use App\Domains\Reconciliation\Actions\RunReconciliationAction;
use App\Domains\Reconciliation\Actions\StartReconciliationAction;
use App\Domains\Revenue\Models\FeeConfiguration;
use App\Domains\Revenue\Models\RevenueHead;
use App\Domains\Ticketing\Models\Ticket;
use App\Domains\Ticketing\Services\TicketVerificationService;
use App\Support\Payments\DTOs\RefundResult;
use App\Support\Payments\Gateways\DemoPaymentGateway;
use Database\Seeders\CorrectionDemoSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\FoundationTestCase;

class CorrectionsTest extends FoundationTestCase
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

    private function finance(): User
    {
        return User::role('Finance Administrator')->first() ?? $this->userWithRole('Finance Administrator');
    }

    private function reviewer(): User
    {
        return User::role('Super Administrator')->first() ?? $this->userWithRole('Super Administrator');
    }

    private function refund(Payment $p, string $amount = '100.10', ?string $key = null): Refund
    {
        return app(RequestRefundAction::class)->execute($this->finance(), $p, ['amount' => $amount, 'reason' => 'Synthetic approved refund request reason.', 'idempotency_key' => $key ?? bin2hex(random_bytes(32))]);
    }

    private function approve(Refund $r, string $decision = 'approved', ?string $key = null): Refund
    {
        return app(ApproveRefundAction::class)->execute($this->reviewer(), $r, $decision, 'Independent synthetic refund review.', $key ?? bin2hex(random_bytes(32)));
    }

    private function process(Refund $r, string $scenario = 'successful', ?string $key = null): Refund
    {
        return app(ProcessRefundAction::class)->execute($this->finance(), $r, $scenario, 'Synthetic refund provider processing.', $key ?? bin2hex(random_bytes(32)));
    }

    private function adjustment(Payment $p, string $amount = '10.00', string $type = 'debit', ?string $key = null, ?FinancialTransaction $source = null): FinancialAdjustment
    {
        return app(RequestAdjustmentAction::class)->execute($this->finance(), $source ?? FinancialTransaction::where('payment_id', $p->id)->where('transaction_type', 'payment')->firstOrFail(), ['amount' => $amount, 'adjustment_type' => $type, 'reason' => 'Synthetic financial correction evidence.', 'idempotency_key' => $key ?? bin2hex(random_bytes(32))]);
    }

    private function reviewAdjustment(FinancialAdjustment $a, string $decision = 'approved', ?string $key = null): FinancialAdjustment
    {
        return app(ApproveAdjustmentAction::class)->execute($this->reviewer(), $a, $decision, 'Independent adjustment decision evidence.', $key ?? bin2hex(random_bytes(32)));
    }

    private function invalid(callable $work, string $field = 'amount'): void
    {
        try {
            $work();
            $this->fail('Expected financial validation failure.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    public function test_partial_refund_preserves_sources_appends_debit_invalidates_verification_and_replays_once(): void
    {
        $n = $this->fixture();
        $p = $this->pay($n);
        $source = FinancialTransaction::first()->getRawOriginal();
        $receipt = $p->receipt->getRawOriginal();
        $requestKey = bin2hex(random_bytes(32));
        $r = $this->refund($p, key: $requestKey);
        $this->assertSame($r->id, $this->refund($p, key: $requestKey)->id);
        $r = $this->approve($r);
        $this->assertDatabaseCount('financial_transactions', 1);
        $processKey = bin2hex(random_bytes(32));
        $r = $this->process($r, key: $processKey);
        $this->assertSame($r->id, $this->process($r, key: $processKey)->id);
        $debit = FinancialTransaction::where('transaction_type', 'refund')->firstOrFail();
        $this->assertSame('100.10', $debit->amount);
        $this->assertSame('debit', $debit->direction);
        $this->assertSame($source['id'], $debit->parent_transaction_id);
        $this->assertSame($source, FinancialTransaction::first()->getRawOriginal());
        $this->assertSame($receipt, $p->receipt->fresh()->getRawOriginal());
        $this->assertSame('successful', $p->fresh()->status->value);
        $this->assertSame('paid', $n['ticket']->fresh()->payment_status->value);
        $this->assertFalse(app(ReceiptVerificationService::class)->safe($p->receipt->fresh())['valid']);
        $this->assertFalse(app(TicketVerificationService::class)->safe($n['ticket']->fresh())['valid']);
        $this->assertDatabaseCount('financial_transactions', 2);
    }

    public function test_cumulative_full_refund_sets_only_approved_payment_statuses(): void
    {
        $n = $this->fixture();
        $p = $this->pay($n);
        $this->process($this->approve($this->refund($p, '100.00')));
        $this->process($this->approve($this->refund($p, '400.10')));
        $this->assertSame('refunded', $p->fresh()->status->value);
        $this->assertSame('refunded', $n['ticket']->fresh()->payment_status->value);
        $this->assertSame('paid', $n['ticket']->fresh()->ticket_status->value);
        $this->assertDatabaseCount('financial_transactions', 3);
        $this->invalid(fn () => $this->refund($p, '0.01'));
    }

    public function test_failed_processing_has_no_debit_retry_and_pending_resolution_are_safe(): void
    {
        $p = $this->pay($this->fixture());
        $r = $this->approve($this->refund($p));
        $r = $this->process($r, 'failed');
        $this->assertSame('failed', $r->status->value);
        $this->assertNotNull($r->processed_at);
        $this->assertDatabaseCount('financial_transactions', 1);
        $r = $this->process($r, 'processing');
        $this->assertNull($r->processed_at);
        $this->assertDatabaseCount('financial_transactions', 1);
        $r = $this->process($r);
        $this->assertSame('successful', $r->status->value);
        $this->assertDatabaseCount('financial_transactions', 2);
        $this->invalid(fn () => $this->process($r));
    }

    public function test_rejected_refund_releases_capacity_and_cannot_process(): void
    {
        $p = $this->pay($this->fixture());
        $r = $this->approve($this->refund($p, '500.10'), 'rejected');
        $this->assertSame($this->reviewer()->id, $r->rejected_by);
        $this->assertNull($r->approved_by);
        $this->invalid(fn () => $this->process($r));
        $this->refund($p, '500.10');
        $this->assertDatabaseCount('financial_transactions', 1);
    }

    public function test_self_approval_and_auditor_mutations_are_forbidden(): void
    {
        $p = $this->pay($this->fixture());
        $r = $this->refund($p);
        $a = $this->adjustment($p);
        $f = $this->finance();
        $auditor = $this->userWithRole('Auditor');
        foreach ([$f, $auditor] as $actor) {
            $this->actingAs($actor)->post('/finance/refunds/'.$r->public_id.'/review', ['decision' => 'approved', 'reason' => 'Review approval evidence.', 'idempotency_key' => bin2hex(random_bytes(32))])->assertForbidden();
            $this->post('/finance/adjustments/'.$a->public_id.'/review', ['decision' => 'approved', 'reason' => 'Review approval evidence.', 'idempotency_key' => bin2hex(random_bytes(32))])->assertForbidden();
        }
        $this->actingAs($auditor)->post('/payments/'.$p->public_id.'/refund', [])->assertForbidden();
        $this->post('/finance/refunds/'.$r->public_id.'/process', [])->assertForbidden();
        $this->post('/finance/adjustments', [])->assertForbidden();
        $this->assertSame('requested', $r->fresh()->status->value);
    }

    public function test_scope_uses_original_ticket_geography_and_manage_access(): void
    {
        $n = $this->fixture();
        $p = $this->pay($n);
        $r = $this->refund($p);
        $local = $this->userWithRole('LGA Administrator');
        $other = Lga::create(['code' => 'B', 'name' => 'Other', 'status' => 'active']);
        $local->lgas()->attach($other, ['access_level' => 'manage', 'created_at' => now()]);
        $this->actingAs($local)->get('/finance/refunds/'.$r->public_id)->assertForbidden();
        $this->get('/finance/refunds')->assertInertia(fn (Assert $a) => $a->has('records.data', 0));
        $this->get('/payments/'.$p->public_id.'/refund')->assertForbidden();
        $n['park']->update(['lga_id' => $other->id]);
        $this->get('/finance/refunds/'.$r->public_id)->assertForbidden();
        $local->lgas()->attach($n['lga'], ['access_level' => 'view', 'created_at' => now()]);
        $this->get('/finance/refunds/'.$r->public_id)->assertOk();
        $this->get('/payments/'.$p->public_id.'/refund')->assertForbidden();
        $local->lgas()->updateExistingPivot($n['lga']->id, ['access_level' => 'manage']);
        $this->get('/payments/'.$p->public_id.'/refund')->assertOk();
    }

    public function test_refunds_and_debit_adjustments_share_pending_reservations(): void
    {
        $p = $this->pay($this->fixture());
        $r = $this->refund($p, '400.00');
        $this->invalid(fn () => $this->refund($p, '100.11'));
        $a = $this->adjustment($p, '100.10');
        $this->invalid(fn () => $this->adjustment($p, '0.01'));
        $this->invalid(fn () => $this->refund($p, '0.01'));
        $this->reviewAdjustment($a);
        $this->process($this->approve($r));
        $this->assertSame('0.00', app(FinancialIntegrityService::class)->remaining($p)['balance']);
        $this->invalid(fn () => $this->refund($p, '0.01'));
    }

    public function test_credit_adjustment_does_not_allow_refund_reservations_above_provider_amount(): void
    {
        $p = $this->pay($this->fixture());
        $this->reviewAdjustment($this->adjustment($p, '500.10', 'credit'));
        $this->refund($p, '400.00');
        $this->invalid(fn () => $this->refund($p, '100.11'));
        $this->refund($p, '100.10');
        $this->assertDatabaseCount('refunds', 2);
    }

    public function test_failed_refund_retry_revalidates_capacity(): void
    {
        $p = $this->pay($this->fixture());
        $r = $this->process($this->approve($this->refund($p, '500.10')), 'failed');
        $this->refund($p, '500.10');
        $this->invalid(fn () => $this->process($r));
        $this->assertDatabaseCount('financial_transactions', 1);
    }

    public function test_credit_adjustment_does_not_increase_provider_refund_entitlement(): void
    {
        $p = $this->pay($this->fixture());
        $a = $this->reviewAdjustment($this->adjustment($p, '100.00', 'credit'));
        $this->invalid(fn () => $this->refund($p, '500.11'));
        $this->process($this->approve($this->refund($p, '500.10')));
        $this->assertSame('100.00', app(FinancialIntegrityService::class)->remaining($p)['balance']);
        $this->assertSame('refunded', $p->fresh()->status->value);
        $this->assertSame('credit', FinancialTransaction::where('transaction_type', 'adjustment')->first()->direction);
    }

    public function test_adjustment_links_exact_selected_parent_and_rejection_adds_no_ledger(): void
    {
        $p = $this->pay($this->fixture());
        $first = $this->reviewAdjustment($this->adjustment($p));
        $debit = FinancialTransaction::where('transaction_type', 'adjustment')->first();
        $second = $this->reviewAdjustment($this->adjustment($p, '0.01', 'credit', source: $debit));
        $entry = FinancialTransaction::where('transaction_reference', 'ADJ-'.$second->adjustment_reference)->first();
        $this->assertSame($debit->id, $entry->parent_transaction_id);
        $this->assertSame('0.01', $entry->amount);
        $a = $this->reviewAdjustment($this->adjustment($p), 'rejected');
        $this->assertNull($a->approved_by);
        $this->assertNull($a->approved_at);
        $this->assertDatabaseCount('financial_transactions', 3);
        $this->invalid(fn () => $this->reviewAdjustment($a));
    }

    public function test_adjustment_request_and_decision_replays_do_not_duplicate_entries(): void
    {
        $p = $this->pay($this->fixture());
        $k = bin2hex(random_bytes(32));
        $a = $this->adjustment($p, key: $k);
        $this->assertSame($a->id, $this->adjustment($p, key: $k)->id);
        $rk = bin2hex(random_bytes(32));
        $this->reviewAdjustment($a, key: $rk);
        $this->reviewAdjustment($a, key: $rk);
        $this->assertDatabaseCount('financial_transactions', 2);
        $this->invalid(fn () => $this->adjustment($p, '11.00', key: $k), 'idempotency_key');
    }

    public function test_reversal_is_blocked_by_pending_or_successful_corrections(): void
    {
        $p = $this->pay($this->fixture());
        $r = $this->refund($p);
        $this->invalid(fn () => app(ReversePaymentAction::class)->execute($this->finance(), $p, 'Synthetic reversal request.', bin2hex(random_bytes(32))), 'reason');
        $this->process($this->approve($r));
        $this->invalid(fn () => app(ReversePaymentAction::class)->execute($this->finance(), $p, 'Synthetic reversal request.', bin2hex(random_bytes(32))), 'reason');
        $this->assertSame('successful', $p->fresh()->status->value);
    }

    public function test_invalid_amounts_and_missing_reasons_cannot_write(): void
    {
        $p = $this->pay($this->fixture());
        foreach (['0', '-1', '1.001', '01.00', '1e5', '10000000000000.00', 'NaN', '1e9999999999999'] as $amount) {
            $this->invalid(fn () => $this->refund($p, $amount));
        }$this->invalid(fn () => app(RequestRefundAction::class)->execute($this->finance(), $p, ['amount' => '1', 'reason' => ' ', 'idempotency_key' => bin2hex(random_bytes(32))]), 'reason');
        $this->assertDatabaseCount('refunds', 0);
        $this->assertDatabaseCount('financial_transactions', 1);
    }

    public function test_confirmation_is_actor_payload_and_operation_bound(): void
    {
        $p = $this->pay($this->fixture());
        $k = bin2hex(random_bytes(32));
        $r = $this->refund($p, key: $k);
        $this->invalid(fn () => $this->refund($p, '1.00', $k), 'idempotency_key');
        $this->invalid(fn () => $this->adjustment($p, key: $k), 'idempotency_key');
        $this->invalid(fn () => app(RequestRefundAction::class)->execute($this->reviewer(), $p, ['amount' => '100.10', 'reason' => 'Synthetic approved refund request reason.', 'idempotency_key' => $k]), 'idempotency_key');
        $this->assertDatabaseCount('refunds', 1);
    }

    public function test_revoked_approval_permission_blocks_even_idempotent_replay(): void
    {
        $p = $this->pay($this->fixture());
        $r = $this->refund($p);
        $k = bin2hex(random_bytes(32));
        $this->approve($r, key: $k);
        $reviewer = $this->reviewer();
        $reviewer->syncRoles(['Auditor']);
        $this->actingAs($reviewer)->post('/finance/refunds/'.$r->public_id.'/review', ['decision' => 'approved', 'reason' => 'Independent synthetic refund review.', 'idempotency_key' => $k])->assertForbidden();
    }

    public function test_gateway_amount_mismatch_rolls_back_processing(): void
    {
        $p = $this->pay($this->fixture());
        $r = $this->approve($this->refund($p));
        $this->mock(DemoPaymentGateway::class)->shouldReceive('refund')->once()->andReturn(new RefundResult($r->refund_reference, 'DEMO-'.$r->refund_reference, '99.00', 'NGN', RefundStatus::Successful));
        $count = FinancialAuditLog::count();
        $this->invalid(fn () => $this->process($r));
        $this->assertSame('approved', $r->fresh()->status->value);
        $this->assertDatabaseCount('financial_transactions', 1);
        $this->assertSame($count, FinancialAuditLog::count());
    }

    public function test_audit_failure_rolls_back_correction_and_workflow(): void
    {
        $p = $this->pay($this->fixture());
        $r = $this->approve($this->refund($p));
        $this->mock(FinancialAuditService::class)->shouldReceive('recordEntity')->andThrow(new \RuntimeException('Audit unavailable'));
        try {
            $this->process($r);
            $this->fail('Expected rollback');
        } catch (\RuntimeException $e) {
            $this->assertSame('Audit unavailable', $e->getMessage());
        }$this->assertDatabaseCount('financial_transactions', 1);
        $this->assertSame('approved', $r->fresh()->status->value);
    }

    public function test_financial_request_terms_and_deletion_are_guarded(): void
    {
        $p = $this->pay($this->fixture());
        $r = $this->refund($p);
        $a = $this->adjustment($p);
        foreach ([$r, $a, FinancialTransaction::first()] as $m) {
            try {
                $m->update(['amount' => '1.00']);
                $this->fail('Expected immutable terms');
            } catch (\LogicException) {
                $this->addToAssertionCount(1);
            }try {
                $m->delete();
                $this->fail('Expected retention');
            } catch (\LogicException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_all_six_screens_are_authorized_safe_and_gets_do_not_write(): void
    {
        $p = $this->pay($this->fixture());
        $r = $this->refund($p);
        $a = $this->adjustment($p);
        $counts = [Refund::count(), FinancialAdjustment::count(), FinancialTransaction::count(), FinancialAuditLog::count()];
        $this->actingAs($this->finance());
        foreach (['/finance/refunds' => 'Finance/Refunds/Index', '/payments/'.$p->public_id.'/refund' => 'Finance/Refunds/Create', '/finance/refunds/'.$r->public_id => 'Finance/Refunds/Show', '/finance/adjustments' => 'Finance/Adjustments/Index', '/finance/adjustments/create' => 'Finance/Adjustments/Create', '/finance/adjustments/'.$a->public_id => 'Finance/Adjustments/Show'] as $url => $component) {
            $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertInertia(fn (Assert $v) => $v->component($component));
        }
        $this->assertSame($counts, [Refund::count(), FinancialAdjustment::count(), FinancialTransaction::count(), FinancialAuditLog::count()]);
        $this->actingAs($this->userWithRole('Auditor'))->get('/finance/refunds/'.$r->public_id)->assertInertia(fn (Assert $v) => $v->where('can_review', false)->where('can_process', false));
        $this->get('/finance/adjustments/'.$a->public_id)->assertOk();
        $this->get('/finance/adjustments/create')->assertForbidden();
    }

    public function test_lists_validate_filters_and_support_amount_sort(): void
    {
        $p = $this->pay($this->fixture());
        $this->refund($p, '1.00');
        $r = $this->refund($p, '2.00');
        $this->actingAs($this->finance())->get('/finance/refunds?sort=amount&order=desc&status=requested')->assertInertia(fn (Assert $v) => $v->where('records.data.0.public_id', $r->public_id));
        $this->get('/finance/refunds?sort=password')->assertSessionHasErrors('sort');
        $this->get('/finance/adjustments?status=successful')->assertSessionHasErrors('status');
        $this->get('/finance/refunds?from=2026-10-10&to=2026-10-09')->assertSessionHasErrors('to');
    }

    public function test_demo_processing_is_disabled_in_production(): void
    {
        $p = $this->pay($this->fixture());
        $r = $this->approve($this->refund($p));
        config(['ospm.demo_mode' => false]);
        $this->actingAs($this->finance())->post('/finance/refunds/'.$r->public_id.'/process', ['scenario' => 'successful', 'reason' => 'Synthetic refund outcome reason.', 'idempotency_key' => bin2hex(random_bytes(32))])->assertForbidden();
        $this->assertDatabaseCount('financial_transactions', 1);
    }

    public function test_new_reconciliation_flags_corrections_and_retains_previous_run(): void
    {
        $p = $this->pay($this->fixture());
        $day = now(config('ospm.timezone'))->toDateString();
        $start = fn () => app(StartReconciliationAction::class)->execute($this->finance(), ['from' => $day, 'to' => $day, 'idempotency_key' => bin2hex(random_bytes(32))]);
        $old = app(RunReconciliationAction::class)->execute($start());
        $snapshot = $old->fresh()->getRawOriginal();
        $this->process($this->approve($this->refund($p)));
        $run = app(RunReconciliationAction::class)->execute($start());
        $this->assertSame('reversal_exception', $run->items()->first()->exception_type);
        $this->assertSame($snapshot, $old->fresh()->getRawOriginal());
        $this->assertStringContainsString('corrections', json_encode($run->summary));
    }

    public function test_seed_and_reset_preserve_correction_decisions_and_history(): void
    {
        $this->seed(DatabaseSeeder::class);
        $r = Refund::firstOrFail();
        $a = FinancialAdjustment::firstOrFail();
        $before = [DB::table('refunds')->get()->toJson(), DB::table('financial_adjustments')->get()->toJson(), DB::table('financial_transactions')->get()->toJson()];
        $this->seed(CorrectionDemoSeeder::class);
        $this->artisan('ospm:demo-reset')->assertSuccessful();
        $this->assertSame($before, [DB::table('refunds')->get()->toJson(), DB::table('financial_adjustments')->get()->toJson(), DB::table('financial_transactions')->get()->toJson()]);
        $this->assertSame('successful', $r->fresh()->status->value);
        $this->assertSame('requested', $a->fresh()->status->value);
    }

    public function test_mysql_maximum_precision_survives_partial_and_final_refund(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL DECIMAL production precision check.');
        }
        $n = $this->fixture();
        DB::table('tickets')->where('id', $n['ticket']->id)->update(['amount' => '9999999999999.99']);
        $n['ticket'] = $n['ticket']->fresh();
        $p = $this->pay($n);
        $this->process($this->approve($this->refund($p, '0.01')));
        $this->assertSame('9999999999999.98', app(FinancialIntegrityService::class)->remaining($p)['refundable']);
        $this->process($this->approve($this->refund($p, '9999999999999.98')));
        $this->assertSame('refunded', $p->fresh()->status->value);
        $this->assertSame('9999999999999.98', FinancialTransaction::where('transaction_type', 'refund')->latest('id')->first()->amount);
    }

    public function test_corrections_update_dashboard_net_and_exclude_original_from_new_demo_batches(): void
    {
        $p = $this->pay($this->fixture());
        $this->process($this->approve($this->refund($p, '100.10')));
        $this->reviewAdjustment($this->adjustment($p, '10.00', 'debit'));
        $this->reviewAdjustment($this->adjustment($p, '0.01', 'credit'));
        $day = now(config('ospm.timezone'))->toDateString();
        $finance = $this->actingAs($this->finance())->get('/finance/dashboard?from='.$day.'&to='.$day)->assertOk()->viewData('page')['props']['finance'];
        $this->assertSame('500.11', $finance['summary']['gross']);
        $this->assertSame('110.10', $finance['summary']['debits']);
        $this->assertSame('390.01', $finance['summary']['net']);
        $period = app(FinancePeriod::class)->validate(['from' => $day, 'to' => $day]);
        $this->assertSame(0, app(SettlementService::class)->eligible($period)->count());
        $this->pay($this->fixture('B'));
        FinancialTransaction::create(['transaction_reference' => 'SYN-ORPHAN-CORRECTION', 'transaction_type' => 'adjustment', 'direction' => 'debit', 'amount' => '0.01', 'currency' => 'NGN', 'occurred_at' => now(), 'source' => 'synthetic-import', 'created_at' => now()]);
        $this->assertSame(1, app(SettlementService::class)->eligible($period)->count());
    }

    public function test_late_correction_includes_old_payment_in_new_period_reconciliation(): void
    {
        $p = $this->pay($this->fixture());
        DB::table('tickets')->where('id', $p->ticket_id)->update(['issued_at' => now()->subWeek()]);
        DB::table('payments')->where('id', $p->id)->update(['paid_at' => now()->subWeek()]);
        $this->reviewAdjustment($this->adjustment($p, '0.01', 'credit'));
        $day = now(config('ospm.timezone'))->toDateString();
        $run = app(StartReconciliationAction::class)->execute($this->finance(), ['from' => $day, 'to' => $day, 'idempotency_key' => bin2hex(random_bytes(32))]);
        $run = app(RunReconciliationAction::class)->execute($run);
        $this->assertSame(1, $run->items()->count());
        $this->assertSame('reversal_exception', $run->items()->first()->exception_type);
        $source = $run->summary['evidence'][$run->items()->first()->id]['sources'][0];
        $this->assertCount(1, $source['credits']);
        $this->assertCount(1, $source['corrections']);
    }

    public function test_adjustment_audit_failure_rolls_back_approval_and_new_entry(): void
    {
        $p = $this->pay($this->fixture());
        $a = $this->adjustment($p);
        $this->mock(FinancialAuditService::class)->shouldReceive('recordEntity')->andThrow(new \RuntimeException('Audit unavailable'));
        try {
            $this->reviewAdjustment($a);
            $this->fail('Expected rollback');
        } catch (\RuntimeException $e) {
            $this->assertSame('Audit unavailable', $e->getMessage());
        }
        $this->assertSame('requested', $a->fresh()->status->value);
        $this->assertNull($a->fresh()->approved_by);
        $this->assertDatabaseCount('financial_transactions', 1);
    }

    public function test_financial_audit_records_every_decision_and_chains_previous_hash(): void
    {
        $p = $this->pay($this->fixture());
        $r = $this->refund($p);
        $this->process($this->approve($r));
        $a = $this->reviewAdjustment($this->adjustment($p));
        $events = FinancialAuditLog::where('entity_type', Refund::class)->where('entity_id', $r->id)->get();
        $this->assertSame(['refund.requested', 'refund.approved', 'refund.successful'], $events->pluck('event_type')->all());
        $this->assertSame([$this->finance()->id, $this->reviewer()->id, $this->finance()->id], $events->pluck('actor_user_id')->all());
        $previous = null;
        foreach (FinancialAuditLog::orderBy('id')->get() as $log) {
            $this->assertSame($previous, $log->previous_hash);
            $payload = $log->getRawOriginal();
            unset($payload['id'],$payload['entry_hash']);
            $payload['payload'] = $log->payload;
            $payload['amount'] = $log->amount;
            $this->assertSame($log->entry_hash, app(FinancialAuditService::class)->hash($payload));
            $previous = $log->entry_hash;
        }
    }
}
