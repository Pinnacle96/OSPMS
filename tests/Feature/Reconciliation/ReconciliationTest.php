<?php

namespace Tests\Feature\Reconciliation;

use App\Domains\Audit\Models\FinancialAuditLog;
use App\Domains\Audit\Services\FinancialAuditService;
use App\Domains\Finance\Actions\CreateSettlementAction;
use App\Domains\Finance\Models\FinancialTransaction;
use App\Domains\Finance\Models\Settlement;
use App\Domains\Finance\Models\SettlementItem;
use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Models\User;
use App\Domains\Operators\Models\Operator;
use App\Domains\Parks\Models\Park;
use App\Domains\Payments\Actions\InitiatePaymentAction;
use App\Domains\Payments\Actions\ReversePaymentAction;
use App\Domains\Payments\Models\Payment;
use App\Domains\Reconciliation\Actions\ResolveReconciliationExceptionAction;
use App\Domains\Reconciliation\Actions\RunReconciliationAction;
use App\Domains\Reconciliation\Actions\StartReconciliationAction;
use App\Domains\Reconciliation\Jobs\ProcessReconciliationRun;
use App\Domains\Reconciliation\Models\ReconciliationItem;
use App\Domains\Reconciliation\Models\ReconciliationRun;
use App\Domains\Reconciliation\Services\ReconciliationMatcher;
use App\Domains\Reconciliation\Services\ReconciliationSummaryService;
use App\Domains\Revenue\Models\FeeConfiguration;
use App\Domains\Revenue\Models\RevenueHead;
use App\Domains\Ticketing\Models\Ticket;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ReconciliationDemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\FoundationTestCase;

class ReconciliationTest extends FoundationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ospm.demo_mode' => true, 'ospm.payment_mode' => 'demo', 'ospm.payment_provider' => 'demo']);
        $time = CarbonImmutable::parse('2026-10-08 12:00:00', 'UTC');
        $this->travelTo($time);
        CarbonImmutable::setTestNow($time);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
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

    private function batch(Payment $p, string $scenario = 'matched', ?string $key = null): Settlement
    {
        return app(CreateSettlementAction::class)->execute($this->finance(), ['from' => '2026-10-08', 'to' => '2026-10-08', 'transaction_ids' => FinancialTransaction::where('payment_id', $p->id)->where('direction', 'credit')->pluck('id')->all(), 'scenario' => $scenario, 'idempotency_key' => $key ?? bin2hex(random_bytes(32))]);
    }

    private function finance()
    {
        return User::role('Finance Administrator')->first() ?? $this->userWithRole('Finance Administrator');
    }

    private function reconcile(array $extra = []): ReconciliationRun
    {
        $run = app(StartReconciliationAction::class)->execute($this->finance(), array_replace(['from' => '2026-10-08', 'to' => '2026-10-08', 'idempotency_key' => bin2hex(random_bytes(32))], $extra));

        return app(RunReconciliationAction::class)->execute($run);
    }

    public function test_clean_match_replay_preserves_financial_sources_and_completed_snapshot(): void
    {
        $n = $this->fixture();
        $p = $this->pay($n);
        $key = bin2hex(random_bytes(32));
        $s = $this->batch($p, key: $key);
        $this->assertSame($s->id, $this->batch($p, key: $key)->id);
        $this->assertSame('500.10', $s->gross_amount);
        $this->assertNull($s->government_account_reference);
        $source = DB::table('financial_transactions')->get()->toJson();
        $run = $this->reconcile();
        $item = $run->items()->firstOrFail();
        $this->assertSame('matched', $item->status->value);
        $this->assertSame('0.00', $item->difference_amount);
        $this->assertSame('500.10', $item->actual_amount);
        $this->assertSame('completed', $run->status->value);
        $audits = FinancialAuditLog::count();
        $this->assertSame($run->id, app(RunReconciliationAction::class)->execute($run)->id);
        $this->assertSame($audits, FinancialAuditLog::count());
        $this->assertSame($source, DB::table('financial_transactions')->get()->toJson());
        $this->assertDatabaseCount('reconciliation_items', 1);
        $this->assertDatabaseCount('settlements', 1);
        $event = FinancialAuditLog::where('event_type', 'settlement.created')->firstOrFail();
        $this->assertSame(Settlement::class, $event->entity_type);
        $this->actingAs($this->finance())->get('/finance/dashboard?from=2026-10-08&to=2026-10-08')->assertInertia(fn (Assert $a) => $a->where('finance.pending_reconciliation', 0));
        $entry = FinancialTransaction::first();
        $this->get('/finance/ledger/'.$entry->public_id)->assertInertia(fn (Assert $a) => $a->where('reconciliation.status', 'matched'));
    }

    public function test_missing_payment_and_amount_mismatch_have_explainable_exact_totals(): void
    {
        $a = $this->fixture('A');
        $b = $this->fixture('B');
        $c = $this->fixture('C');
        $this->batch($this->pay($a));
        $this->batch($this->pay($b), 'amount_mismatch');
        $run = $this->reconcile();
        $summary = app(ReconciliationSummaryService::class)->get($run->items()->getQuery());
        $this->assertSame('1500.30', $summary['expected']);
        $this->assertSame('1000.19', $summary['actual']);
        $this->assertSame('500.11', $summary['difference']);
        $this->assertSame(1, $summary['matched']);
        $this->assertSame(2, $summary['exceptions']);
        $missing = $run->items()->where('ticket_id', $c['ticket']->id)->first();
        $mismatch = $run->items()->where('ticket_id', $b['ticket']->id)->first();
        $this->assertSame('ticket_without_payment', $missing->exception_type);
        $this->assertSame('amount_mismatch', $mismatch->exception_type);
        $this->assertSame('0.01', $mismatch->difference_amount);
        $this->assertSame(['amount_mismatch'], $run->summary['evidence'][$mismatch->id]['flags']);
        $this->actingAs($this->finance())->get('/finance/reconciliation/items/'.$mismatch->id)->assertInertia(fn (Assert $p) => $p->component('Finance/Reconciliation/Exception')->where('evidence.sources.0.credits.0.settlements.0.amount', '500.09'));
    }

    public function test_missing_settlement_and_missing_ledger_are_distinct(): void
    {
        $a = $this->fixture('A');
        $b = $this->fixture('B');
        $this->pay($a);
        $p = $this->pay($b);
        DB::table('financial_transactions')->where('payment_id', $p->id)->delete(); // Simulate an incomplete imported lineage.
        $run = $this->reconcile();
        $this->assertSame('missing_settlement', $run->items()->where('ticket_id', $a['ticket']->id)->first()->exception_type);
        $this->assertSame('missing_ledger_entry', $run->items()->where('ticket_id', $b['ticket']->id)->first()->exception_type);
    }

    public function test_duplicate_provider_reference_is_detected_across_scopes_without_exposing_other_record(): void
    {
        $a = $this->fixture('A');
        $b = $this->fixture('B');
        $one = $this->pay($a);
        $two = $this->pay($b);
        $two->update(['provider_reference' => $one->provider_reference]);
        $this->batch($one);
        $this->batch($two);
        $run = $this->reconcile(['park_id' => $a['park']->id]);
        $item = $run->items()->firstOrFail();
        $this->assertSame('duplicate_provider_reference', $item->exception_type);
        $this->assertDatabaseCount('reconciliation_items', 1);
        $this->assertStringNotContainsString($two->payment_reference, json_encode($run->summary));
    }

    public function test_reversal_preserves_settlement_and_new_run_flags_reversal_without_changing_old_match(): void
    {
        $n = $this->fixture();
        $p = $this->pay($n);
        $s = $this->batch($p);
        $before = $this->reconcile();
        app(ReversePaymentAction::class)->execute($this->finance(), $p, 'Synthetic reversal after settlement', bin2hex(random_bytes(32)));
        $after = $this->reconcile();
        $this->assertSame('reversal_exception', $after->items()->first()->exception_type);
        $this->assertSame('matched', $before->items()->first()->status->value);
        $this->assertSame('500.10', $s->fresh()->gross_amount);
        $this->actingAs($this->finance())->get('/finance/reconciliation')->assertInertia(fn (Assert $a) => $a->where('summary.total', 1)->where('summary.open', 1));
    }

    public function test_review_requires_reason_is_idempotent_and_audits_actor_without_money_changes(): void
    {
        $this->fixture();
        $item = $this->reconcile()->items()->first();
        $actor = $this->finance();
        $before = $item->only(['expected_amount', 'actual_amount', 'difference_amount', 'exception_type']);
        $key = bin2hex(random_bytes(32));
        $input = ['status' => 'under_review', 'note' => 'Investigating the missing provider payment.', 'idempotency_key' => $key];
        $action = app(ResolveReconciliationExceptionAction::class);
        $action->execute($actor, $item, $input);
        $count = FinancialAuditLog::count();
        $action->execute($actor, $item, $input);
        $this->assertSame($count, FinancialAuditLog::count());
        $this->assertSame($before, $item->fresh()->only(array_keys($before)));
        $this->assertSame($actor->id, $item->fresh()->resolved_by);
        $this->assertNotNull($item->fresh()->resolved_at);
        $action->execute($actor, $item, ['status' => 'reconciled', 'note' => 'Reviewed supporting records; exception acknowledged in demo.', 'idempotency_key' => bin2hex(random_bytes(32))]);
        $this->assertSame('reconciled', $item->fresh()->status->value);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertDatabaseCount('payments', 0);
        $events = FinancialAuditLog::where('event_type', 'reconciliation.exception_reviewed')->get();
        $this->assertCount(2, $events);
        $this->assertSame($actor->id, $events->last()->actor_user_id);
        $this->assertSame('under_review', $events->last()->payload['from']);
        $this->expectException(ValidationException::class);
        $action->execute($actor, $item, ['status' => 'under_review', 'note' => 'Another review attempt after completion', 'idempotency_key' => bin2hex(random_bytes(32))]);
    }

    public function test_blank_review_and_matched_record_cannot_be_resolved(): void
    {
        $n = $this->fixture();
        $p = $this->pay($n);
        $this->batch($p);
        $item = $this->reconcile()->items()->first();
        $actor = $this->finance();
        $this->actingAs($actor)->post('/finance/reconciliation/items/'.$item->id.'/resolve', ['status' => 'reconciled', 'note' => '', 'idempotency_key' => bin2hex(random_bytes(32))])->assertSessionHasErrors('note');
        $this->post('/finance/reconciliation/items/'.$item->id.'/resolve', ['status' => 'reconciled', 'note' => 'A matched finding should remain matched.', 'idempotency_key' => bin2hex(random_bytes(32))])->assertSessionHasErrors('status');
        $this->assertNull($item->fresh()->resolved_by);
    }

    public function test_scope_filters_all_totals_lists_details_and_retains_historical_ticket_geography(): void
    {
        $a = $this->fixture('A');
        $b = $this->fixture('B');
        $this->batch($this->pay($a));
        $run = $this->reconcile();
        $foreign = $run->items()->where('ticket_id', $b['ticket']->id)->first();
        $local = $run->items()->where('ticket_id', $a['ticket']->id)->first();
        $viewer = $this->userWithRole('LGA Administrator');
        $viewer->lgas()->attach($a['lga'], ['access_level' => 'view', 'created_at' => now()]);
        $a['park']->update(['lga_id' => $b['lga']->id]); // History remains in the original ticket LGA.
        $this->actingAs($viewer)->get('/finance/reconciliation')->assertInertia(fn (Assert $p) => $p->where('summary.total', 1)->where('summary.expected', '500.10')->where('summary.open', 0)->where('items.total', 0));
        $response = $this->get('/finance/reconciliation/runs/'.$run->public_id)->assertInertia(fn (Assert $p) => $p->where('run.summary.total', 1)->where('run.status', 'completed')->where('items.total', 1)->where('items.data.0.ticket_reference', 'TEST-A'));
        $this->assertStringNotContainsString('TEST-B', $response->getContent());
        $this->assertStringNotContainsString('PRIVATE-', json_encode($response->viewData('page')['props']['items']));
        $this->get('/finance/reconciliation/items/'.$foreign->id)->assertForbidden();
        $this->get('/finance/reconciliation/items/'.$local->id)->assertOk();
        $this->get('/finance/settlements')->assertForbidden();
        $this->get('/finance/reconciliation?lga_id='.$b['lga']->id)->assertInertia(fn (Assert $p) => $p->where('summary.total', 0));
        $this->post('/finance/reconciliation/items/'.$local->id.'/resolve', ['status' => 'reconciled', 'note' => 'Forbidden geographic write attempt', 'idempotency_key' => bin2hex(random_bytes(32))])->assertForbidden();
        $unscoped = $this->userWithRole('LGA Administrator');
        $this->actingAs($unscoped)->get('/finance/reconciliation/runs')->assertInertia(fn (Assert $p) => $p->where('records.total', 0));
        $this->get('/finance/reconciliation/runs/'.$run->public_id)->assertForbidden();
    }

    public function test_auditor_and_other_readers_have_no_finance_write_permissions(): void
    {
        $this->fixture();
        $run = $this->reconcile();
        $item = $run->items()->first();
        foreach (['Auditor', 'State Administrator', 'Executive Viewer', 'Revenue Officer'] as $role) {
            $actor = $this->userWithRole($role);
            $this->actingAs($actor)->get('/finance/reconciliation')->assertOk();
            $this->get('/finance/reconciliation/runs/create')->assertForbidden();
            $this->post('/finance/reconciliation/runs', ['from' => '2026-10-08', 'to' => '2026-10-08', 'idempotency_key' => bin2hex(random_bytes(32))])->assertForbidden();
            $this->post('/finance/reconciliation/items/'.$item->id.'/resolve', ['status' => 'reconciled', 'note' => 'Unauthorized resolution attempt', 'idempotency_key' => bin2hex(random_bytes(32))])->assertForbidden();
            $this->get('/finance/settlements/create')->assertForbidden();
        }
        foreach (['Park Manager', 'Collection Agent', 'Ticketing Officer', 'Enforcement Officer', 'Help Desk Officer', 'Transport Operator'] as $role) {
            $this->actingAs($this->userWithRole($role))->get('/finance/reconciliation')->assertForbidden();
        }
    }

    public function test_settlement_money_is_server_calculated_and_cannot_be_batched_twice(): void
    {
        $n = $this->fixture();
        $p = $this->pay($n);
        $ids = FinancialTransaction::pluck('id')->all();
        $this->actingAs($this->finance())->post('/finance/settlements', ['from' => '2026-10-08', 'to' => '2026-10-08', 'transaction_ids' => $ids, 'scenario' => 'matched', 'idempotency_key' => bin2hex(random_bytes(32)), 'gross_amount' => '0.01', 'currency' => 'USD', 'status' => 'reversed', 'government_account_reference' => 'FORGED'])->assertSessionHasNoErrors();
        $s = Settlement::first();
        $this->assertSame('500.10', $s->gross_amount);
        $this->assertSame('NGN', $s->currency);
        $this->assertSame('settled', $s->status->value);
        $this->assertNull($s->government_account_reference);
        $this->expectException(ValidationException::class);
        $this->batch($p);
    }

    public function test_settlement_and_start_confirmation_reject_changed_payload_or_actor(): void
    {
        $n = $this->fixture();
        $p = $this->pay($n);
        $key = bin2hex(random_bytes(32));
        $this->batch($p, key: $key);
        $this->actingAs($this->finance())->post('/finance/settlements', ['from' => '2026-10-08', 'to' => '2026-10-08', 'transaction_ids' => FinancialTransaction::pluck('id')->all(), 'scenario' => 'amount_mismatch', 'idempotency_key' => $key])->assertSessionHasErrors('idempotency_key');
        $start = app(StartReconciliationAction::class);
        $input = ['from' => '2026-10-08', 'to' => '2026-10-08', 'idempotency_key' => bin2hex(random_bytes(32))];
        $run = $start->execute($this->finance(), $input);
        $this->assertSame($run->id, $start->execute($this->finance(), $input)->id);
        $this->expectException(ValidationException::class);
        $start->execute($this->userWithRole('Finance Administrator'), $input);
    }

    public function test_money_and_finding_records_cannot_be_edited_or_deleted(): void
    {
        $n = $this->fixture();
        $this->batch($this->pay($n));
        $run = $this->reconcile();
        foreach ([Settlement::first(), SettlementItem::first(), $run, ReconciliationItem::first()] as $model) {
            try {
                $model->delete();
                $this->fail('Financial deletion accepted');
            } catch (\LogicException $e) {
                $this->assertStringContainsString('retained', $e->getMessage());
            }
        }
        foreach ([[Settlement::first(), 'gross_amount', '0.01'], [SettlementItem::first(), 'amount', '0.01'], [ReconciliationItem::first(), 'expected_amount', '0.01'], [$run, 'provider', 'forged']] as [$model,$key,$value]) {
            try {
                $model->update([$key => $value]);
                $this->fail('Finding edit accepted');
            } catch (\LogicException $e) {
                $this->assertStringContainsString('immutable', $e->getMessage());
            }
        }
    }

    public function test_matcher_failure_rolls_back_partial_findings_and_queue_failure_is_retained(): void
    {
        $this->fixture();
        $run = app(StartReconciliationAction::class)->execute($this->finance(), ['from' => '2026-10-08', 'to' => '2026-10-08', 'idempotency_key' => bin2hex(random_bytes(32))]);
        $mock = \Mockery::mock(ReconciliationMatcher::class);
        $mock->shouldReceive('match')->andReturnUsing(function ($r) {
            $r->items()->create(['expected_amount' => '1.00', 'actual_amount' => '0.00', 'difference_amount' => '1.00', 'status' => 'exception', 'exception_type' => 'unknown']);
            throw new \RuntimeException('Synthetic matching failure');
        });
        $this->app->instance(ReconciliationMatcher::class, $mock);
        try {
            app(RunReconciliationAction::class)->execute($run);
            $this->fail('Expected matching failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic matching failure', $e->getMessage());
        }
        $this->assertDatabaseCount('reconciliation_items', 0);
        $this->assertSame('queued', $run->fresh()->status->value);
        (new ProcessReconciliationRun($run->id))->failed(new \RuntimeException('private internal error'));
        $this->assertSame('failed', $run->fresh()->status->value);
        $this->assertStringNotContainsString('private internal', json_encode($run->fresh()->summary));
    }

    public function test_audit_failure_rolls_back_settlement_and_confirmation(): void
    {
        $n = $this->fixture();
        $p = $this->pay($n);
        $mock = \Mockery::mock(FinancialAuditService::class);
        $mock->shouldReceive('recordEntity')->andThrow(new \RuntimeException('Synthetic audit failure'));
        $this->app->instance(FinancialAuditService::class, $mock);
        $before = DB::table('idempotency_keys')->count();
        try {
            $this->batch($p);
            $this->fail('Audit failure expected');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic audit failure', $e->getMessage());
        }
        $this->assertDatabaseCount('settlements', 0);
        $this->assertDatabaseCount('settlement_items', 0);
        $this->assertSame($before, DB::table('idempotency_keys')->count());
    }

    public function test_period_validation_and_lagos_day_boundaries(): void
    {
        $a = $this->fixture('A');
        $b = $this->fixture('B');
        $c = $this->fixture('C');
        $d = $this->fixture('D');
        foreach ([[$a, '2026-10-07 22:59:59'], [$b, '2026-10-07 23:00:00'], [$c, '2026-10-08 22:59:59'], [$d, '2026-10-08 23:00:00']] as [$n,$time]) {
            DB::table('tickets')->where('id', $n['ticket']->id)->update(['issued_at' => $time]);
        }
        $run = $this->reconcile();
        $this->assertSame([$b['ticket']->id, $c['ticket']->id], $run->items()->orderBy('ticket_id')->pluck('ticket_id')->all());
        $this->actingAs($this->finance())->post('/finance/reconciliation/runs', ['from' => '2024-01-01', 'to' => '2026-10-08', 'idempotency_key' => bin2hex(random_bytes(32))])->assertSessionHasErrors('days');
        $this->post('/finance/reconciliation/runs', ['from' => '2026-10-08', 'to' => '2026-10-08', 'lga_id' => $a['lga']->id, 'park_id' => $b['park']->id, 'idempotency_key' => bin2hex(random_bytes(32))])->assertSessionHasErrors('scope');
    }

    public function test_production_and_live_configuration_refuse_demo_settlement(): void
    {
        $n = $this->fixture();
        $p = $this->pay($n);
        $input = ['from' => '2026-10-08', 'to' => '2026-10-08', 'transaction_ids' => FinancialTransaction::pluck('id')->all(), 'scenario' => 'matched', 'idempotency_key' => bin2hex(random_bytes(32))];
        config(['ospm.payment_mode' => 'live']);
        $this->actingAs($this->finance())->post('/finance/settlements', $input)->assertForbidden();
        config(['ospm.payment_mode' => 'demo']);
        $this->app['env'] = 'production';
        $this->withSession(['_token' => 'synthetic-token'])->post('/finance/settlements', $input + ['_token' => 'synthetic-token'])->assertForbidden();
        $this->assertDatabaseCount('settlements', 0);
    }

    public function test_all_eight_screens_and_filters_work_without_read_side_financial_writes(): void
    {
        $n = $this->fixture();
        $s = $this->batch($this->pay($n), 'amount_mismatch');
        $run = $this->reconcile();
        $item = $run->items()->first();
        $counts = [FinancialAuditLog::count(), Settlement::count(), ReconciliationItem::count()];
        $paths = ['/finance/settlements', '/finance/settlements/create', '/finance/settlements/'.$s->public_id, '/finance/reconciliation', '/finance/reconciliation/runs', '/finance/reconciliation/runs/create', '/finance/reconciliation/runs/'.$run->public_id, '/finance/reconciliation/items/'.$item->id];
        foreach ($paths as $path) {
            $this->actingAs($this->finance())->get($path)->assertOk();
        }
        $this->get('/finance/reconciliation?exception_type=amount_mismatch')->assertInertia(fn (Assert $p) => $p->where('items.total', 1));
        $this->get('/finance/reconciliation?exception_type=missing_settlement')->assertInertia(fn (Assert $p) => $p->where('items.total', 0));
        $this->get('/finance/reconciliation?status=forged')->assertSessionHasErrors('status');
        $this->assertSame($counts, [FinancialAuditLog::count(), Settlement::count(), ReconciliationItem::count()]);
    }

    public function test_demo_seeding_is_repeatable_and_retains_reviewed_exception(): void
    {
        config(['ospm.demo_password' => 'SyntheticDemoPassword42']);
        $this->seed(DatabaseSeeder::class);
        $item = ReconciliationItem::where('exception_type', 'amount_mismatch')->firstOrFail();
        app(ResolveReconciliationExceptionAction::class)->execute($this->finance(), $item, ['status' => 'reconciled', 'note' => 'Synthetic seed retention review with evidence.', 'idempotency_key' => bin2hex(random_bytes(32))]);
        $counts = [Ticket::count(), Payment::count(), Settlement::count(), ReconciliationRun::count(), ReconciliationItem::count(), FinancialAuditLog::count()];
        $this->seed(ReconciliationDemoSeeder::class);
        $this->artisan('ospm:demo-reset')->assertSuccessful();
        $this->assertSame($counts, [Ticket::count(), Payment::count(), Settlement::count(), ReconciliationRun::count(), ReconciliationItem::count(), FinancialAuditLog::count()]);
        $this->assertSame('reconciled', $item->fresh()->status->value);
        $this->assertSame('0.01', $item->fresh()->difference_amount);
        $this->assertTrue(ReconciliationItem::where('status', 'matched')->exists());
        $this->assertTrue(ReconciliationItem::where('exception_type', 'ticket_without_payment')->exists());
    }

    public function test_unknown_ledger_finding_does_not_replace_its_ticket_obligation_in_latest_totals(): void
    {
        $n = $this->fixture();
        $p = $this->pay($n);
        $this->batch($p);
        FinancialTransaction::create(['transaction_reference' => 'SYN-UNKNOWN', 'ticket_id' => $n['ticket']->id, 'transaction_type' => 'payment', 'direction' => 'credit', 'amount' => '3.00', 'currency' => 'NGN', 'occurred_at' => now(), 'source' => 'synthetic-import', 'created_at' => now()]);
        $run = $this->reconcile();
        $this->assertSame(2, $run->items()->count());
        $this->actingAs($this->finance())->get('/finance/reconciliation')->assertInertia(fn (Assert $a) => $a->where('summary.total', 2)->where('summary.expected', '500.10')->where('summary.matched', 1)->where('summary.open', 1));
        $this->reconcile();
        $this->get('/finance/reconciliation')->assertInertia(fn (Assert $a) => $a->where('summary.total', 2));
    }

    public function test_late_payment_includes_prior_ticket_once_and_pending_failed_attempts_do_not_add_obligations(): void
    {
        $n = $this->fixture();
        DB::table('tickets')->where('id', $n['ticket']->id)->update(['issued_at' => '2026-10-01 12:00:00']);
        $this->pay($n, 'failed');
        $p = $this->pay($n);
        $this->batch($p);
        $run = $this->reconcile();
        $this->assertSame(1, $run->items()->count());
        $this->assertSame('matched', $run->items()->first()->status->value);
        $this->assertSame('500.10', $run->summary['expected']);
    }

    public function test_finding_detects_missing_provider_reference_and_imported_amount_corruption(): void
    {
        $n = $this->fixture();
        $p = $this->pay($n);
        $this->batch($p);
        $p->update(['provider_reference' => null]);
        DB::table('payments')->where('id', $p->id)->update(['amount' => '499.00']);
        $run = $this->reconcile();
        $item = $run->items()->first();
        $this->assertSame('amount_mismatch', $item->exception_type);
        $this->assertContains('unknown', $run->summary['evidence'][$item->id]['flags']);
        $this->assertSame('500.10', $item->expected_amount);
        $this->assertSame('500.10', $item->actual_amount);
    }

    public function test_newer_missing_settlement_can_be_matched_in_new_run_without_overwriting_prior_findings(): void
    {
        $n = $this->fixture();
        $p = $this->pay($n);
        $before = $this->reconcile();
        $this->batch($p);
        $after = $this->reconcile();
        $this->assertSame('missing_settlement', $before->items()->first()->exception_type);
        $this->assertSame('matched', $after->items()->first()->status->value);
        $this->actingAs($this->finance())->get('/finance/reconciliation')->assertInertia(fn (Assert $a) => $a->where('summary.total', 1)->where('summary.open', 0)->where('summary.actual', '500.10'));
    }

    public function test_settlement_selection_period_and_reversal_are_rechecked_inside_action(): void
    {
        $n = $this->fixture();
        $p = $this->pay($n);
        $ids = FinancialTransaction::pluck('id')->all();
        $actor = $this->finance();
        $this->actingAs($actor)->post('/finance/settlements', ['from' => '2026-10-01', 'to' => '2026-10-01', 'transaction_ids' => $ids, 'scenario' => 'matched', 'idempotency_key' => bin2hex(random_bytes(32))])->assertSessionHasErrors('transaction_ids');
        app(ReversePaymentAction::class)->execute($actor, $p, 'Synthetic stale settlement selection', bin2hex(random_bytes(32)));
        $this->post('/finance/settlements', ['from' => '2026-10-08', 'to' => '2026-10-08', 'transaction_ids' => $ids, 'scenario' => 'matched', 'idempotency_key' => bin2hex(random_bytes(32))])->assertSessionHasErrors('transaction_ids');
        $this->assertDatabaseCount('settlements', 0);
    }

    public function test_malformed_review_input_is_validation_error_and_financial_pages_are_private(): void
    {
        $this->fixture();
        $run = $this->reconcile();
        $item = $run->items()->first();
        $this->actingAs($this->finance())->post('/finance/reconciliation/items/'.$item->id.'/resolve', ['status' => 'under_review', 'note' => ['invalid'], 'idempotency_key' => bin2hex(random_bytes(32))])->assertSessionHasErrors('note');
        foreach (['/finance/reconciliation', '/finance/reconciliation/runs/'.$run->public_id, '/finance/reconciliation/items/'.$item->id, '/finance/settlements'] as $path) {
            $this->get($path)->assertHeader('Cache-Control', 'no-store, private');
        }
    }

    public function test_queued_job_completes_and_duplicate_delivery_returns_retained_result(): void
    {
        $this->fixture();
        $run = app(StartReconciliationAction::class)->execute($this->finance(), ['from' => '2026-10-08', 'to' => '2026-10-08', 'idempotency_key' => bin2hex(random_bytes(32))]);
        $job = new ProcessReconciliationRun($run->id);
        $job->handle(app(RunReconciliationAction::class));
        $audits = FinancialAuditLog::count();
        $job->handle(app(RunReconciliationAction::class));
        $this->assertSame('completed_with_exceptions', $run->fresh()->status->value);
        $this->assertDatabaseCount('reconciliation_items', 1);
        $this->assertSame($audits, FinancialAuditLog::count());
    }

    public function test_summary_keeps_mysql_maximum_decimal_precision_and_batch_overflow_is_rejected(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL DECIMAL production precision check.');
        }
        $a = $this->fixture('A');
        $b = $this->fixture('B');
        foreach ([$a, $b] as $n) {
            DB::table('tickets')->where('id', $n['ticket']->id)->update(['amount' => '9999999999999.99']);
        }
        $one = $this->pay(['actor' => $a['actor'], 'ticket' => $a['ticket']->fresh()]);
        $two = $this->pay(['actor' => $b['actor'], 'ticket' => $b['ticket']->fresh()]);
        $this->actingAs($this->finance())->post('/finance/settlements', ['from' => '2026-10-08', 'to' => '2026-10-08', 'scenario' => 'matched', 'transaction_ids' => FinancialTransaction::pluck('id')->all(), 'idempotency_key' => bin2hex(random_bytes(32))])->assertSessionHasErrors('transaction_ids');
        $this->assertDatabaseCount('settlements', 0);
        $this->batch($one);
        $this->batch($two);
        $run = $this->reconcile();
        $this->assertSame('19999999999999.98', $run->summary['expected']);
        $this->assertSame('19999999999999.98', $run->summary['actual']);
        $this->assertSame('0.00', $run->summary['difference']);
    }

    public function test_imported_payment_without_ticket_is_detected_without_weakening_application_schema(): void
    {
        $n = $this->fixture();
        $payment = $this->pay($n);
        // Corrupt legacy import exists only inside this disposable test transaction.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        } else {
            DB::statement('PRAGMA defer_foreign_keys=ON');
        }
        try {
            DB::table('payments')->where('id', $payment->id)->update(['ticket_id' => 999999]);
        } finally {
            if (DB::getDriverName() === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }
        $run = $this->reconcile();
        $item = $run->items()->where('payment_id', $payment->id)->firstOrFail();
        $this->assertSame('payment_without_ticket', $item->exception_type);
        $this->assertNull($item->ticket_id);
        $viewer = $this->userWithRole('LGA Administrator');
        $viewer->lgas()->attach($n['lga'], ['access_level' => 'view', 'created_at' => now()]);
        $this->actingAs($viewer)->get('/finance/reconciliation/items/'.$item->id)->assertForbidden();
    }

    public function test_incomplete_or_mismatched_credit_lineage_is_not_eligible_for_demo_settlement(): void
    {
        $n = $this->fixture();
        $p = $this->pay($n);
        DB::table('financial_transactions')->where('payment_id', $p->id)->update(['amount' => '1.00']);
        $this->actingAs($this->finance())->get('/finance/settlements/create')->assertInertia(fn (Assert $a) => $a->where('eligible_count', 0));
        $this->expectException(ValidationException::class);
        $this->batch($p);
    }

    public function test_list_date_filters_and_sorting_are_server_validated_and_scoped(): void
    {
        $a = $this->fixture('A');
        $b = $this->fixture('B');
        $this->batch($this->pay($a));
        $this->batch($this->pay($b), 'amount_mismatch');
        $run = $this->reconcile();
        $this->actingAs($this->finance())->get('/finance/settlements?sort=gross_amount&order=asc&from=2026-10-08&to=2026-10-08')->assertInertia(fn (Assert $p) => $p->where('records.total', 2)->where('records.data.0.gross_amount', '500.09'));
        $this->get('/finance/settlements?from=2026-10-09')->assertInertia(fn (Assert $p) => $p->where('records.total', 0));
        $this->get('/finance/reconciliation/runs?sort=period_start&order=asc&to=2026-10-07')->assertInertia(fn (Assert $p) => $p->where('records.total', 0));
        $this->get('/finance/reconciliation/runs/'.$run->public_id.'?sort=difference_amount&order=desc')->assertInertia(fn (Assert $p) => $p->where('items.data.0.difference_amount', '0.01'));
        $this->get('/finance/reconciliation?from=2026-10-09')->assertInertia(fn (Assert $p) => $p->where('summary.total', 0));
        foreach (['/finance/settlements', '/finance/reconciliation/runs', '/finance/reconciliation'] as $path) {
            $this->get($path.'?sort=provider_metadata')->assertSessionHasErrors('sort');
            $this->get($path.'?from=2026-10-09&to=2026-10-08')->assertSessionHasErrors('to');
        }
    }
}
