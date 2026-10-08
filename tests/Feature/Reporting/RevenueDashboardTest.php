<?php

namespace Tests\Feature\Reporting;

use App\Domains\Finance\Models\FinancialTransaction;
use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Models\User;
use App\Domains\Operators\Models\Operator;
use App\Domains\Parks\Models\Park;
use App\Domains\Payments\Actions\InitiatePaymentAction;
use App\Domains\Payments\Actions\ReversePaymentAction;
use App\Domains\Payments\Models\Payment;
use App\Domains\Revenue\Models\FeeConfiguration;
use App\Domains\Revenue\Models\RevenueHead;
use App\Domains\Ticketing\Models\Ticket;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\FoundationTestCase;

class RevenueDashboardTest extends FoundationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ospm.demo_mode' => true, 'ospm.payment_mode' => 'demo', 'ospm.payment_provider' => 'demo']);
        $this->at('2026-10-10 12:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function at(string $utc): void
    {
        $time = CarbonImmutable::parse($utc, 'UTC');
        $this->travelTo($time);
        CarbonImmutable::setTestNow($time);
    }

    private function network(string $tag): array
    {
        $actor = $this->userWithRole('Collection Agent');
        $lga = Lga::create(['code' => $tag, 'name' => 'Synthetic LGA '.$tag, 'status' => 'active']);
        $park = Park::create(['lga_id' => $lga->id, 'park_code' => $tag, 'name' => 'Synthetic Park '.$tag, 'address' => 'Synthetic address', 'status' => 'active']);
        $actor->parks()->attach($park, ['access_level' => 'manage', 'created_at' => now()]);
        $operator = Operator::create(['operator_number' => $tag, 'name' => 'PRIVATE-OPERATOR-'.$tag, 'phone' => 'PRIVATE-PHONE', 'status' => 'approved']);
        $head = RevenueHead::create(['code' => $tag, 'name' => 'Synthetic head '.$tag, 'frequency' => 'daily', 'status' => 'active']);
        $fee = FeeConfiguration::create(['revenue_head_id' => $head->id, 'amount' => '500.10', 'currency' => 'NGN', 'effective_from' => now()->subYear(), 'status' => 'active', 'priority' => 0]);

        return compact('actor', 'lga', 'park', 'operator', 'head', 'fee');
    }

    private function attempt(array $n, string $amount, string $scenario = 'successful', string $time = '2026-10-08 10:00:00'): Payment
    {
        $this->at($time);
        $ticket = Ticket::create(['ticket_reference' => 'SYN-'.Str::ulid(), 'revenue_head_id' => $n['head']->id, 'fee_configuration_id' => $n['fee']->id, 'lga_id' => $n['lga']->id, 'park_id' => $n['park']->id, 'operator_id' => $n['operator']->id, 'fee_code_snapshot' => 'SYN', 'fee_name_snapshot' => 'Synthetic fee', 'amount' => $amount, 'currency' => 'NGN', 'issued_by' => $n['actor']->id, 'issued_at' => now(), 'ticket_status' => 'pending', 'payment_status' => 'unpaid', 'verification_token' => bin2hex(random_bytes(32)), 'context_snapshot' => ['lga' => ['name' => $n['lga']->name], 'park' => ['name' => $n['park']->name], 'driver' => ['name' => 'PRIVATE-DRIVER'], 'vehicle' => ['registration' => 'SYN-'.$n['park']->park_code]]]);

        return app(InitiatePaymentAction::class)->execute($n['actor'], $ticket, $scenario, bin2hex(random_bytes(32)));
    }

    private function finance(User $user, string $query = 'from=2026-10-08&to=2026-10-10', string $path = '/finance/dashboard'): array
    {
        $response = $this->actingAs($user)->get($path.'?'.$query)->assertOk();

        return $response->viewData('page')['props']['finance'];
    }

    public function test_ledger_totals_and_every_breakdown_reconcile_and_failed_pending_attempts_add_no_revenue(): void
    {
        $a = $this->network('A');
        $b = $this->network('B');
        $one = $this->attempt($a, '100.10');
        $this->attempt($b, '200.20');
        $this->attempt($a, '900.00', 'failed');
        $this->attempt($b, '800.00', 'pending');
        $this->at('2026-10-09 10:00:00');
        $admin = $this->userWithRole('Finance Administrator');
        app(ReversePaymentAction::class)->execute($admin, $one, 'Synthetic reversal', bin2hex(random_bytes(32)));
        $this->at('2026-10-10 12:00:00');
        $finance = $this->finance($admin);
        $this->assertSame('300.30', $finance['summary']['gross']);
        $this->assertSame('100.10', $finance['summary']['debits']);
        $this->assertSame('200.20', $finance['summary']['net']);
        $this->assertSame(3, $finance['summary']['transactions']);
        $this->assertSame([1, 1, 1, 1, 0], array_column($finance['payment_status'], 'count'));
        $this->assertSame(4, $finance['payment_channels'][0]['count']);
        foreach (['revenue_trend', 'revenue_by_lga', 'revenue_by_park', 'revenue_by_revenue_head'] as $key) {
            $total = BigDecimal::zero();
            foreach ($finance[$key] as $row) {
                $total = $total->plus($row['net']);
            }
            $this->assertSame('200.20', (string) $total->toScale(2), $key);
        }
        $this->assertSame(['300.30', '-100.10', '0.00'], array_column($finance['revenue_trend'], 'net'));
        $this->assertSame('0.00', $finance['summary']['today']['net']);
        $this->assertTrue($finance['reconciliation_available']);
        $this->assertSame(2, $finance['pending_reconciliation']);
        $this->assertStringNotContainsString('PRIVATE-', json_encode($finance));
        $this->get($finance['ledger_url'])->assertInertia(fn (Assert $p) => $p->where('records.total', 3));
    }

    public function test_lagos_midnight_boundaries_match_ledger_and_payment_drilldowns_exactly(): void
    {
        $n = $this->network('A');
        $this->attempt($n, '1.00', time: '2026-10-07 22:59:59');
        $this->attempt($n, '0.10', time: '2026-10-07 23:00:00');
        $this->attempt($n, '0.20', time: '2026-10-08 22:59:59');
        $this->attempt($n, '2.00', time: '2026-10-08 23:00:00');
        $this->at('2026-10-08 12:00:00');
        $finance = $this->finance($this->userWithRole('Finance Administrator'), 'from=2026-10-08&to=2026-10-08');
        $this->assertSame('0.30', $finance['summary']['net']);
        $this->assertSame('0.30', $finance['summary']['today']['net']);
        $this->assertSame('2026-10-08', $finance['revenue_trend'][0]['date']);
        foreach (['ledger_url', 'payments_url'] as $key) {
            $this->get($finance[$key])->assertInertia(fn (Assert $p) => $p->where('records.total', 2));
        }
        $this->get('/finance/ledger?from=2026-10-08&to=2026-10-08')->assertInertia(fn (Assert $p) => $p->where('records.total', 2));
    }

    public function test_debit_only_period_is_negative_and_original_credit_stays_in_its_original_period(): void
    {
        $n = $this->network('A');
        $payment = $this->attempt($n, '500.10', time: '2026-09-30 12:00:00');
        $this->at('2026-10-08 12:00:00');
        $admin = $this->userWithRole('Finance Administrator');
        app(ReversePaymentAction::class)->execute($admin, $payment, 'Synthetic reversal', bin2hex(random_bytes(32)));
        $current = $this->finance($admin, 'from=2026-10-08&to=2026-10-08');
        $this->assertSame('0.00', $current['summary']['gross']);
        $this->assertSame('-500.10', $current['summary']['net']);
        $this->assertSame('-500.10', $current['summary']['month']['net']);
        $this->assertSame('-500.10', $current['revenue_trend'][0]['net']);
        $old = $this->finance($admin, 'from=2026-09-30&to=2026-09-30');
        $this->assertSame('500.10', $old['summary']['gross']);
        $this->assertSame('500.10', $old['summary']['net']);
        $this->assertSame(1, $old['payment_status'][3]['count']);
    }

    public function test_geographic_filters_cannot_expand_scope_on_any_dashboard_or_disclose_foreign_names(): void
    {
        $a = $this->network('A');
        $b = $this->network('B');
        $this->attempt($a, '100.10');
        $this->attempt($b, '999.99');
        $local = $this->userWithRole('LGA Administrator');
        $local->lgas()->attach($a['lga'], ['access_level' => 'view', 'created_at' => now()]);
        $path = '/lgas/'.$a['lga']->public_id.'/dashboard';
        $finance = $this->finance($local, path: $path);
        $this->assertSame('100.10', $finance['summary']['net']);
        $this->assertStringNotContainsString('Synthetic Park B', json_encode($finance));
        $blocked = $this->finance($local, 'park_id='.$b['park']->id, $path);
        $this->assertSame('0.00', $blocked['summary']['net']);
        $this->assertSame([], $blocked['revenue_by_park']);
        $this->get('/parks/'.$b['park']->public_id.'/dashboard')->assertForbidden();
        $local->givePermissionTo('view_state_dashboard', 'view_state_revenue');
        $state = $this->finance($local, path: '/dashboard');
        $this->assertSame('100.10', $state['summary']['net']);
        $this->get('/dashboard?lga_id='.$b['lga']->id)->assertInertia(fn (Assert $p) => $p->where('finance.summary.net', '0.00'));
    }

    public function test_park_financial_summary_does_not_grant_ledger_access_and_helpdesk_gets_no_financial_props(): void
    {
        $n = $this->network('A');
        $this->attempt($n, '500.10');
        $manager = $this->userWithRole('Park Manager');
        $manager->parks()->attach($n['park'], ['access_level' => 'view', 'created_at' => now()]);
        $path = '/parks/'.$n['park']->public_id.'/dashboard';
        $finance = $this->finance($manager, path: $path);
        $this->assertSame('500.10', $finance['summary']['net']);
        $this->assertNull($finance['ledger_url']);
        $this->assertNull($finance['revenue_by_park'][0]['href']);
        $this->assertStringStartsWith('/payments/', $finance['recent_transactions'][0]['href']);
        $this->get('/finance/ledger')->assertForbidden();
        $help = $this->userWithRole('Help Desk Officer');
        $help->parks()->attach($n['park'], ['access_level' => 'view', 'created_at' => now()]);
        $this->actingAs($help)->get($path)->assertInertia(fn (Assert $p) => $p->where('finance', null)->where('metrics.7.value', null));
    }

    public function test_historical_ticket_lga_membership_survives_a_park_move_and_archival(): void
    {
        $a = $this->network('A');
        $b = $this->network('B');
        $this->attempt($a, '500.10');
        DB::table('parks')->where('id', $a['park']->id)->update(['lga_id' => $b['lga']->id, 'deleted_at' => now()]);
        $local = $this->userWithRole('LGA Administrator');
        $local->lgas()->attach($a['lga'], ['access_level' => 'view', 'created_at' => now()]);
        $finance = $this->finance($local, path: '/lgas/'.$a['lga']->public_id.'/dashboard');
        $this->assertSame('500.10', $finance['summary']['net']);
        $this->assertSame($a['lga']->id, (int) $finance['revenue_by_lga'][0]['id']);
        $this->assertSame($a['park']->id, (int) $finance['revenue_by_park'][0]['id']);
        $other = $this->finance($this->userWithRole('State Administrator'), 'lga_id='.$b['lga']->id, '/dashboard');
        $this->assertSame('0.00', $other['summary']['net']);
    }

    public function test_dimensions_and_channel_filter_all_financial_measures_and_their_detail_queries(): void
    {
        $a = $this->network('A');
        $b = $this->network('B');
        $this->attempt($a, '100.10');
        $this->attempt($b, '200.20');
        $this->attempt($a, '900.00', 'failed');
        $admin = $this->userWithRole('Finance Administrator');
        $query = 'from=2026-10-08&to=2026-10-08&park_id='.$a['park']->id.'&revenue_head_id='.$a['head']->id.'&channel=demo';
        $finance = $this->finance($admin, $query);
        $this->assertSame('100.10', $finance['summary']['net']);
        $this->assertSame('100.10', $finance['summary']['today']['net']);
        $this->assertSame('100.10', $finance['summary']['month']['net']);
        $this->get($finance['ledger_url'])->assertInertia(fn (Assert $p) => $p->where('records.total', 1));
        $this->get($finance['payments_url'])->assertInertia(fn (Assert $p) => $p->where('records.total', 2));
        foreach (['revenue_by_lga', 'revenue_by_park', 'revenue_by_revenue_head'] as $key) {
            $this->get($finance[$key][0]['href'])->assertInertia(fn (Assert $p) => $p->where('records.total', 1));
        }
        $empty = $this->finance($admin, $query.'&channel=transfer');
        $this->assertSame('0.00', $empty['summary']['net']);
        $this->assertSame([], $empty['revenue_trend']);
    }

    public function test_dashboard_route_permissions_and_executive_read_only_surface(): void
    {
        foreach (['Super Administrator', 'State Administrator', 'Finance Administrator', 'Auditor', 'Executive Viewer'] as $role) {
            $this->actingAs($this->userWithRole($role))->get('/executive/dashboard')->assertInertia(fn (Assert $p) => $p->component('Dashboard/Executive'));
        }
        foreach (['Super Administrator', 'State Administrator', 'Finance Administrator', 'Auditor', 'Revenue Officer'] as $role) {
            $this->actingAs($this->userWithRole($role))->get('/finance/dashboard')->assertInertia(fn (Assert $p) => $p->component('Dashboard/Revenue'));
        }
        $this->actingAs($this->userWithRole('Executive Viewer'))->get('/finance/dashboard')->assertForbidden();
        $this->post('/executive/dashboard')->assertStatus(405);
        $this->actingAs($this->userWithRole('Revenue Officer'))->get('/executive/dashboard')->assertForbidden();
        foreach (['Collection Agent', 'LGA Administrator', 'Park Manager', 'Transport Operator'] as $role) {
            $this->actingAs($this->userWithRole($role));
            foreach (['/dashboard', '/executive/dashboard', '/finance/dashboard'] as $path) {
                $this->get($path)->assertForbidden();
            }
        }
    }

    public function test_dashboard_permission_without_revenue_permission_or_scope_never_exposes_statewide_finance(): void
    {
        $n = $this->network('A');
        $this->attempt($n, '500.10');
        $user = User::factory()->create();
        $user->givePermissionTo('view_state_dashboard');
        $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $p) => $p->where('finance', null)->where('metrics.0.value', null));
        $user->givePermissionTo('view_state_revenue');
        $finance = $this->finance($user, path: '/dashboard');
        $this->assertSame('0.00', $finance['summary']['net']);
        $this->assertSame([], $finance['revenue_by_lga']);
    }

    public function test_date_validation_end_only_and_default_month_to_date_are_consistent(): void
    {
        $admin = $this->userWithRole('State Administrator');
        $this->actingAs($admin)->get('/dashboard')->assertInertia(fn (Assert $p) => $p->where('filters.from', '2026-10-01')->where('filters.to', '2026-10-10'));
        $this->get('/dashboard?to=2026-09-30')->assertOk()->assertInertia(fn (Assert $p) => $p->where('filters.from', '2026-09-30'));
        foreach (['from=2026-10-10&to=2026-10-08', 'from=2024-01-01&to=2026-10-08'] as $query) {
            $this->get('/dashboard?'.$query)->assertSessionHasErrors('to');
        }
        $this->get('/dashboard?from=invalid&channel=unknown')->assertSessionHasErrors(['from', 'channel']);
    }

    public function test_exact_decimal_aggregation_empty_states_no_get_mutations_and_recent_history_limit(): void
    {
        $n = $this->network('A');
        for ($i = 0; $i < 7; $i++) {
            $this->attempt($n, '0.10');
        }
        $tables = ['payments', 'receipts', 'financial_transactions', 'financial_audit_logs', 'idempotency_keys'];
        $before = array_map(fn ($table) => DB::table($table)->count(), $tables);
        $finance = $this->finance($this->userWithRole('Finance Administrator'));
        $this->assertSame('0.70', $finance['summary']['net']);
        $this->assertCount(6, $finance['recent_transactions']);
        $this->assertSame($before, array_map(fn ($table) => DB::table($table)->count(), $tables));
        $this->assertSame(7, FinancialTransaction::count());
    }

    public function test_mysql_aggregates_remain_exact_beyond_one_record_decimal_capacity(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL DECIMAL precision check; SQLite numeric affinity is not the production money store.');
        }
        $n = $this->network('A');
        $this->attempt($n, '9999999999999.99');
        $this->attempt($n, '9999999999999.99');
        $finance = $this->finance($this->userWithRole('Finance Administrator'));
        $this->assertSame('19999999999999.98', $finance['summary']['net']);
        $this->assertSame('19999999999999.98', $finance['revenue_trend'][0]['net']);
    }

    public function test_executive_lands_on_its_own_read_only_dashboard_and_local_context_cannot_be_overridden(): void
    {
        $this->actingAs($this->userWithRole('Executive Viewer'))->get('/')->assertRedirect('/executive/dashboard');
        $n = $this->network('A');
        $this->attempt($n, '500.10');
        $admin = $this->userWithRole('State Administrator');
        $finance = $this->finance($admin, 'lga_id=999999', '/lgas/'.$n['lga']->public_id.'/dashboard');
        $this->assertSame('500.10', $finance['summary']['net']);
        $this->get($finance['ledger_url'])->assertInertia(fn (Assert $p) => $p->where('records.total', 1));
    }

    public function test_lga_dashboard_requires_lga_view_permission_without_an_unrelated_park_permission(): void
    {
        $n = $this->network('A');
        $this->attempt($n, '500.10');
        $user = User::factory()->create();
        $user->givePermissionTo('view_lga', 'view_lga_revenue');
        $user->lgas()->attach($n['lga'], ['access_level' => 'view', 'created_at' => now()]);
        $finance = $this->finance($user, path: '/lgas/'.$n['lga']->public_id.'/dashboard');
        $this->assertSame('500.10', $finance['summary']['net']);
        $this->assertNull($finance['ledger_url']);
    }

    public function test_configured_timezone_transition_keeps_each_ledger_entry_on_its_local_day(): void
    {
        config(['ospm.timezone' => 'America/New_York']);
        $n = $this->network('A');
        $this->attempt($n, '0.10', time: '2026-03-08 04:30:00');
        $this->attempt($n, '0.20', time: '2026-03-09 04:30:00');
        $finance = $this->finance($this->userWithRole('Finance Administrator'), 'from=2026-03-07&to=2026-03-09');
        $this->assertSame(['0.10', '0.00', '0.20'], array_column($finance['revenue_trend'], 'net'));
        $this->get($finance['ledger_url'])->assertOk()->assertSessionHasNoErrors()->assertInertia(fn (Assert $p) => $p->where('records.total', 2));
    }
}
