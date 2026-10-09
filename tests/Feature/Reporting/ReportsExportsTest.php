<?php

namespace Tests\Feature\Reporting;

use App\Domains\Assignments\Models\DriverAssignment;
use App\Domains\Audit\Models\FinancialAuditLog;
use App\Domains\Complaints\Models\Complaint;
use App\Domains\Drivers\Models\Driver;
use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Models\User;
use App\Domains\Incidents\Models\Incident;
use App\Domains\Operators\Models\Operator;
use App\Domains\Parks\Models\Park;
use App\Domains\Payments\Actions\InitiatePaymentAction;
use App\Domains\Payments\Actions\ReversePaymentAction;
use App\Domains\Reconciliation\Models\ReconciliationItem;
use App\Domains\Reconciliation\Models\ReconciliationRun;
use App\Domains\Reporting\Actions\GenerateReportExportAction;
use App\Domains\Reporting\Models\ReportExport;
use App\Domains\Reporting\Services\ReportCatalog;
use App\Domains\Reporting\Services\ReportExportAccess;
use App\Domains\Reporting\Services\ReportExportService;
use App\Domains\Reporting\Services\ReportFileWriter;
use App\Domains\Revenue\Models\FeeConfiguration;
use App\Domains\Revenue\Models\RevenueHead;
use App\Domains\System\Models\MediaAttachment;
use App\Domains\Ticketing\Models\Ticket;
use App\Domains\Vehicles\Models\Vehicle;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\FoundationTestCase;

class ReportsExportsTest extends FoundationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-09 12:00:00', 'UTC'));
        config(['ospm.demo_mode' => true, 'ospm.payment_mode' => 'demo', 'ospm.payment_provider' => 'demo']);
        Storage::fake('local');
    }

    private function network(string $tag = 'A'): array
    {
        $actor = $this->userWithRole('Collection Agent');
        $lga = Lga::create(['code' => $tag, 'name' => 'Synthetic LGA '.$tag, 'status' => 'active']);
        $park = Park::create(['lga_id' => $lga->id, 'park_code' => $tag, 'name' => 'Synthetic Park '.$tag, 'address' => 'PRIVATE-ADDRESS', 'status' => 'active']);
        $actor->parks()->attach($park, ['access_level' => 'manage', 'created_at' => now()]);
        $operator = Operator::create(['operator_number' => $tag, 'name' => 'Synthetic Operator '.$tag, 'phone' => 'PRIVATE-PHONE', 'status' => 'approved']);
        $operator->parks()->attach($park, ['status' => 'active', 'created_at' => now()]);
        $driver = Driver::create(['driver_number' => $tag, 'first_name' => 'Synthetic', 'last_name' => $tag, 'status' => 'active', 'phone' => 'PRIVATE-DRIVER', 'licence_number' => 'PRIVATE-LICENCE']);
        $vehicle = Vehicle::create(['vehicle_number' => $tag, 'registration_number' => 'SYN-'.$tag, 'vehicle_type' => 'bus', 'status' => 'active', 'owner_phone' => 'PRIVATE-OWNER']);
        $assignment = DriverAssignment::create(['driver_id' => $driver->id, 'vehicle_id' => $vehicle->id, 'operator_id' => $operator->id, 'park_id' => $park->id, 'starts_at' => now()->subDay(), 'is_primary' => true, 'status' => 'active']);
        $head = RevenueHead::create(['code' => $tag, 'name' => 'Synthetic head '.$tag, 'frequency' => 'daily', 'status' => 'active']);
        $fee = FeeConfiguration::create(['revenue_head_id' => $head->id, 'amount' => '500.10', 'currency' => 'NGN', 'effective_from' => now()->subYear(), 'status' => 'active', 'priority' => 0]);

        return compact('actor', 'lga', 'park', 'operator', 'driver', 'vehicle', 'assignment', 'head', 'fee');
    }

    private function pay(array $n, string $amount = '100.10', string $scenario = 'successful', ?string $utc = null)
    {
        if ($utc) {
            $this->travelTo(CarbonImmutable::parse($utc, 'UTC'));
        }
        $t = Ticket::create(['ticket_reference' => 'SYN-'.Str::ulid(), 'revenue_head_id' => $n['head']->id, 'fee_configuration_id' => $n['fee']->id, 'lga_id' => $n['lga']->id, 'park_id' => $n['park']->id, 'operator_id' => $n['operator']->id, 'driver_id' => $n['driver']->id, 'vehicle_id' => $n['vehicle']->id, 'fee_code_snapshot' => 'SYN', 'fee_name_snapshot' => 'Synthetic fee', 'amount' => $amount, 'currency' => 'NGN', 'issued_by' => $n['actor']->id, 'issued_at' => now(), 'ticket_status' => 'pending', 'payment_status' => 'unpaid', 'verification_token' => bin2hex(random_bytes(32)), 'context_snapshot' => []]);

        return app(InitiatePaymentAction::class)->execute($n['actor'], $t, $scenario, bin2hex(random_bytes(32)));
    }

    private function props(User $u, string $type, array $f = []): array
    {
        return $this->actingAs($u)->get('/reports/'.$type.'?'.http_build_query(['from' => '2026-10-01', 'to' => '2026-10-10', ...$f]))->assertOk()->viewData('page')['props'];
    }

    private function request(User $u, string $type = 'daily-revenue', array $extra = []): ReportExport
    {
        return app(ReportExportService::class)->request($u, $type, ['from' => '2026-10-01', 'to' => '2026-10-10', 'format' => 'csv', 'idempotency_key' => bin2hex(random_bytes(32)), ...$extra]);
    }

    private function download(User $u, ReportExport $e)
    {
        return $this->actingAs($u)->get('/reports/exports/'.$e->response_payload['public_id'].'/download');
    }

    private function summary(array $p): array
    {
        return array_column($p['summary'], 'value', 'key');
    }

    public function test_all_eleven_reports_and_three_screens_have_empty_states_and_private_headers(): void
    {
        $u = $this->userWithRole('Super Administrator');
        $this->actingAs($u)->get('/reports')->assertInertia(fn (Assert $p) => $p->component('Reports/Index')->has('reports', 11));
        $this->get('/reports/exports')->assertInertia(fn (Assert $p) => $p->component('Reports/Exports')->has('records.data', 0));
        foreach (array_keys(ReportCatalog::TYPES) as $type) {
            $response = $this->get('/reports/'.$type)->assertOk()->assertInertia(fn (Assert $p) => $p->component('Reports/Show')->has('records.data', 0));
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $this->assertTrue($response->viewData('page')['encryptHistory']);
        }
        $this->get('/reports/unknown')->assertNotFound();
    }

    public function test_ledger_groups_totals_and_transactions_match_original_sources_without_financial_writes(): void
    {
        $a = $this->network();
        $b = $this->network('B');
        $p = $this->pay($a);
        $this->pay($b, '200.20');
        $this->pay($a, '900.00', 'failed');
        $this->pay($b, '800.00', 'pending');
        $u = $this->userWithRole('Finance Administrator');
        app(ReversePaymentAction::class)->execute($u, $p, 'Synthetic reversal', bin2hex(random_bytes(32)));
        $before = DB::table('financial_transactions')->get()->toJson();
        $audits = FinancialAuditLog::count();
        foreach (['daily-revenue', 'monthly-revenue', 'revenue-by-park', 'revenue-by-lga'] as $type) {
            $props = $this->props($u, $type);
            $this->assertSame(['gross' => '300.30', 'debits' => '100.10', 'net' => '200.20', 'transactions' => 3], $this->summary($props));
            $net = BigDecimal::zero();
            foreach ($props['records']['data'] as $r) {
                $net = $net->plus($r['net']);
            }$this->assertSame('200.20', (string) $net->toScale(2));
        }
        $props = $this->props($u, 'transactions');
        $this->assertSame(['records' => 4, 'amount' => '2000.30'], $this->summary($props));
        $this->assertSame(4, $props['records']['total']);
        $this->request($u);
        $this->assertSame($before, DB::table('financial_transactions')->get()->toJson());
        $this->assertSame($audits, FinancialAuditLog::count());
    }

    public function test_lagos_day_and_month_boundaries_are_exclusive_at_end_and_exact(): void
    {
        $n = $this->network();
        foreach ([['9.00', '2026-09-30 22:59:59'], ['0.10', '2026-09-30 23:00:00'], ['0.20', '2026-10-01 22:59:59'], ['8.00', '2026-10-01 23:00:00']] as [$amount,$time]) {
            $this->pay($n, $amount, utc: $time);
        }$u = $this->userWithRole('Finance Administrator');
        $f = ['from' => '2026-10-01', 'to' => '2026-10-01'];
        foreach (['daily-revenue', 'monthly-revenue'] as $type) {
            $p = $this->props($u, $type, $f);
            $this->assertSame('0.30', $this->summary($p)['net']);
            $this->assertSame(1, $p['records']['total']);
        }$this->assertSame('2026-10-01', $this->props($u, 'daily-revenue', $f)['records']['data'][0]['day']);
        $this->assertSame('2026-10', $this->props($u, 'monthly-revenue', $f)['records']['data'][0]['month']);
    }

    public function test_reconciliation_reports_each_completed_snapshot_and_excludes_incomplete_runs(): void
    {
        $n = $this->network();
        $p = $this->pay($n);
        $u = $this->userWithRole('Finance Administrator');
        foreach (['completed', 'completed_with_exceptions', 'running'] as $i => $status) {
            $r = ReconciliationRun::create(['reconciliation_reference' => 'RUN-'.$i, 'status' => $status, 'started_at' => now(), 'completed_at' => $status === 'running' ? null : now(), 'period_start' => now()->subDay(), 'period_end' => now(), 'started_by' => $u->id, 'created_at' => now(), 'summary' => []]);
            ReconciliationItem::create(['reconciliation_run_id' => $r->id, 'ticket_id' => $p->ticket_id, 'payment_id' => $p->id, 'status' => 'matched', 'expected_amount' => '100.10', 'actual_amount' => '100.10', 'difference_amount' => '0.00', 'created_at' => now()]);
        }
        $v = $this->props($u, 'reconciliation');
        $this->assertSame(2, $v['records']['total']);
        $this->assertSame('200.20', $this->summary($v)['expected_amount']);
        $this->assertStringContainsString('snapshot', $v['report']['basis']);
    }

    public function test_registry_and_operational_reports_exclude_contacts_notes_and_private_identifiers(): void
    {
        $n = $this->network();
        Incident::create(['incident_reference' => 'INC-A', 'park_id' => $n['park']->id, 'operator_id' => $n['operator']->id, 'driver_id' => $n['driver']->id, 'vehicle_id' => $n['vehicle']->id, 'category' => 'Safety', 'description' => 'PRIVATE-DESCRIPTION', 'status' => 'reported', 'reported_by' => $n['actor']->id, 'occurred_at' => now()]);
        Complaint::create(['complaint_reference' => 'CMP-A', 'park_id' => $n['park']->id, 'category' => 'Service', 'complainant_name' => 'PRIVATE-COMPLAINANT', 'complainant_phone' => 'PRIVATE-PHONE', 'description' => 'PRIVATE-DESCRIPTION', 'source' => 'staff', 'status' => 'submitted', 'submitted_by' => $n['actor']->id]);
        $u = $this->userWithRole('State Administrator');
        foreach (['drivers', 'vehicles', 'operators', 'incidents', 'complaints'] as $type) {
            $p = $this->props($u, $type);
            $this->assertSame(1, $p['records']['total']);
            $this->assertSame(1, $this->summary($p)['records']);
            $this->assertStringNotContainsString('PRIVATE-', json_encode([$p['records'], $p['options']]));
        }
    }

    public function test_scoped_report_rows_summary_and_filter_choices_cannot_leak_other_parks(): void
    {
        $a = $this->network();
        $b = $this->network('B');
        $this->pay($a);
        $this->pay($b, '200.20');
        $u = $this->userWithRole('LGA Administrator');
        $u->lgas()->attach($a['lga'], ['access_level' => 'view', 'created_at' => now()]);
        foreach (['daily-revenue', 'transactions', 'drivers', 'vehicles', 'operators'] as $type) {
            $p = $this->props($u, $type);
            $this->assertSame(1, $p['records']['total']);
            $this->assertStringNotContainsString($b['park']->public_id, json_encode($p['options']));
            $this->actingAs($u)->get('/reports/'.$type.'?park='.$b['park']->public_id)->assertSessionHasErrors('park');
        }
        $this->assertSame('100.10', $this->summary($this->props($u, 'daily-revenue'))['net']);
    }

    public function test_operator_own_scope_does_not_expand_to_other_operators_in_the_same_park(): void
    {
        $a = $this->network();
        $b = $this->network('B');
        $b['actor']->parks()->attach($a['park'], ['access_level' => 'manage', 'created_at' => now()]);
        $b['park'] = $a['park'];
        $b['lga'] = $a['lga'];
        $b['operator']->parks()->attach($a['park'], ['status' => 'active', 'created_at' => now()]);
        $b['assignment']->update(['park_id' => $a['park']->id]);
        $this->pay($a);
        $this->pay($b, '200.20');
        $u = $this->userWithRole('Transport Operator');
        $u->operators()->attach($a['operator'], ['access_level' => 'view', 'created_at' => now()]);
        $u->parks()->attach($a['park'], ['access_level' => 'view', 'created_at' => now()]);
        $u->givePermissionTo('view_payment');
        foreach (['operators', 'drivers', 'vehicles', 'transactions'] as $type) {
            $this->assertSame(1, $this->props($u, $type)['records']['total']);
        }$this->actingAs($u)->get('/reports/operators?operator='.$b['operator']->public_id)->assertSessionHasErrors('operator');
    }

    public function test_financial_filters_use_historical_ticket_geography_after_park_moves_and_archival(): void
    {
        $a = $this->network();
        $b = $this->network('B');
        $this->pay($a);
        $u = $this->userWithRole('LGA Administrator');
        $u->lgas()->attach($a['lga'], ['access_level' => 'view', 'created_at' => now()]);
        $a['park']->update(['lga_id' => $b['lga']->id]);
        $a['park']->delete();
        $p = $this->props($u, 'daily-revenue', ['lga' => $a['lga']->public_id, 'park' => $a['park']->public_id]);
        $this->assertSame('100.10', $this->summary($p)['net']);
    }

    public function test_every_supported_financial_filter_and_status_channel_intersection_matches_source(): void
    {
        $a = $this->network();
        $b = $this->network('B');
        $this->pay($a);
        $this->pay($b, '200.20');
        $u = $this->userWithRole('Finance Administrator');
        $f = [];
        foreach (['lga', 'park', 'operator', 'driver', 'vehicle'] as $k) {
            $f[$k] = $a[$k]->public_id;
        }$f['revenue_head'] = $a['head']->public_id;
        $f['status'] = 'successful';
        $f['channel'] = 'demo';
        $this->assertSame('100.10', $this->summary($this->props($u, 'daily-revenue', $f))['net']);
        $this->assertSame(1, $this->props($u, 'transactions', $f)['records']['total']);
        $this->assertSame(0, $this->props($u, 'transactions', [...$f, 'channel' => 'transfer'])['records']['total']);
    }

    public function test_registry_operator_driver_only_filter_is_applied_and_filters_share_one_assignment(): void
    {
        $a = $this->network();
        $b = $this->network('B');
        $u = $this->userWithRole('State Administrator');
        $this->assertSame(1, $this->props($u, 'operators', ['driver' => $a['driver']->public_id])['records']['total']);
        $this->assertSame(0, $this->props($u, 'drivers', ['operator' => $a['operator']->public_id, 'vehicle' => $b['vehicle']->public_id])['records']['total']);
    }

    public function test_invalid_and_unsupported_filters_and_date_ranges_are_rejected(): void
    {
        $u = $this->userWithRole('State Administrator');
        $this->actingAs($u);
        foreach ([['from' => '2026-10-10', 'to' => '2026-10-09'], ['from' => '2020-01-01', 'to' => '2026-10-09']] as $f) {
            $this->get('/reports/drivers?'.http_build_query($f))->assertSessionHasErrors('to');
        }$this->get('/reports/drivers?channel=demo')->assertSessionHasErrors('channel');
        $this->get('/reports/transactions?status=not-real')->assertSessionHasErrors('status');
        $this->get('/reports/drivers?driver=1')->assertSessionHasErrors('driver');
    }

    public function test_view_roles_have_no_export_and_underlying_domain_permissions_are_required(): void
    {
        $u = $this->userWithRole('Executive Viewer');
        $this->props($u, 'daily-revenue');
        $this->actingAs($u)->post('/reports/daily-revenue/exports', ['format' => 'csv', 'idempotency_key' => bin2hex(random_bytes(32))])->assertForbidden();
        $this->get('/reports/drivers')->assertForbidden();
        $help = $this->userWithRole('Help Desk Officer');
        $this->actingAs($help)->get('/reports')->assertOk();
        $this->get('/reports/transactions')->assertForbidden();
        $none = User::factory()->create();
        $this->actingAs($none)->get('/reports')->assertForbidden();
        $this->get('/reports/exports')->assertForbidden();
        $this->assertSame(0, ReportExport::count());
    }

    public function test_preview_is_paginated_and_filter_options_are_bounded_without_rejecting_valid_selected_values(): void
    {
        $u = $this->userWithRole('State Administrator');
        for ($i = 0; $i < 30; $i++) {
            Operator::create(['operator_number' => 'OP-'.$i, 'name' => 'Operator '.$i, 'status' => 'approved']);
        }config(['reports.filter_options_limit' => 3]);
        $p = $this->props($u, 'operators');
        $this->assertCount(25, $p['records']['data']);
        $this->assertCount(3, $p['options']['operator']);
        $last = Operator::orderByDesc('id')->first();
        $p = $this->props($u, 'operators', ['operator' => $last->public_id]);
        $this->assertSame(1, $p['records']['total']);
        $this->assertContains($last->public_id, array_column($p['options']['operator'], 'public_id'));
    }

    public function test_csv_is_exact_private_and_replay_returns_one_file_and_one_completion_audit(): void
    {
        $n = $this->network();
        $this->pay($n, '0.10');
        $this->pay($n, '0.20');
        $u = $this->userWithRole('Finance Administrator');
        $key = bin2hex(random_bytes(32));
        $e = $this->request($u, extra: ['idempotency_key' => $key]);
        $again = $this->request($u, extra: ['idempotency_key' => $key]);
        $this->assertSame($e->id, $again->id);
        $this->assertSame('completed', $e->response_payload['status']);
        $this->assertSame(1, MediaAttachment::where('category', 'report_export')->count());
        $this->assertSame(1, Activity::where('description', 'report_export_completed')->count());
        $r = $this->download($u, $e)->assertOk();
        $this->assertStringContainsString('0.30', $r->streamedContent());
        $this->assertStringContainsString('attachment', $r->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));
        $this->actingAs($u)->get('/reports/exports')->assertInertia(fn (Assert $p) => $p->missing('records.data.0.coverage_ids')->missing('records.data.0.scope_signature')->missing('records.data.0.criteria'));
    }

    public function test_csv_formula_text_is_neutralized_without_changing_negative_money_or_quoted_fields(): void
    {
        $n = $this->network();
        $n['operator']->update(['name' => " \t=HYPERLINK(\"https://example.test\")"]);
        $u = $this->userWithRole('State Administrator');
        $e = $this->request($u, 'operators');
        $r = $this->download($u, $e)->assertOk();
        $this->assertStringContainsString("' \t=HYPERLINK", $r->streamedContent());
        $w = app(ReportFileWriter::class);
        $this->assertSame('-0.10', $w->csvValue('-0.10', 'money'));
        $this->assertSame("'@SUM(A1)", $w->csvValue('@SUM(A1)', 'text'));
    }

    public function test_xlsx_uses_explicit_string_cells_no_formulas_and_exact_decimal_text(): void
    {
        $n = $this->network();
        $this->pay($n, '100.10');
        $u = $this->userWithRole('Finance Administrator');
        $e = $this->request($u, extra: ['format' => 'xlsx']);
        $this->assertSame('completed', $e->response_payload['status']);
        $m = MediaAttachment::where('public_id', $e->response_payload['media_public_id'])->firstOrFail();
        $z = new \ZipArchive;
        $this->assertTrue($z->open(Storage::disk('local')->path($m->path)));
        $xml = $z->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringNotContainsString('<f>', $xml);
        $this->assertStringContainsString('t="s"', $xml);
        $this->assertStringContainsString('100.10', $z->getFromName('xl/sharedStrings.xml'));
        $this->assertStringContainsString('Summary', $z->getFromName('xl/workbook.xml'));
        $z->close();
        $this->download($u, $e)->assertOk();
    }

    public function test_pdf_is_valid_and_hostile_names_are_escaped_without_fetching_remote_content(): void
    {
        $n = $this->network();
        $n['operator']->update(['name' => '<script>alert(1)</script> <img src="http://127.0.0.1/private">']);
        $u = $this->userWithRole('State Administrator');
        $e = $this->request($u, 'operators', ['format' => 'pdf']);
        $this->assertSame('completed', $e->response_payload['status']);
        $this->assertStringStartsWith('%PDF-', $this->download($u, $e)->assertOk()->streamedContent());
        $r = app(ReportCatalog::class)->get('operators');
        $html = view('reports.pdf', ['report' => $r, 'criteria' => ['from' => '2026-10-01', 'to' => '2026-10-10'], 'columns' => $r->columns(), 'rows' => iterator_to_array($r->export($u, ['from' => '2026-10-01', 'to' => '2026-10-10'])), 'summary' => $r->summary($u, ['from' => '2026-10-01', 'to' => '2026-10-10'])])->render();
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_large_exports_queue_once_and_duplicate_job_execution_is_idempotent(): void
    {
        config(['queue.connections.database.after_commit' => true]);
        $n = $this->network();
        $this->pay($n);
        config(['reports.queue_threshold' => 0]);
        $u = $this->userWithRole('Finance Administrator');
        $key = bin2hex(random_bytes(32));
        $e = $this->request($u, extra: ['idempotency_key' => $key]);
        $this->request($u, extra: ['idempotency_key' => $key]);
        $this->assertSame('queued', $e->response_payload['status']);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertSame('reports', DB::table('jobs')->value('queue'));
        $this->assertSame(0, MediaAttachment::count());
        $this->download($u, $e)->assertStatus(409);
        $a = app(GenerateReportExportAction::class);
        $a->execute($e->id);
        $a->execute($e->id);
        $this->assertSame('completed', $e->refresh()->response_payload['status']);
        $this->assertSame(1, MediaAttachment::count());
        $this->assertSame(1, Activity::where('description', 'report_export_completed')->count());
    }

    public function test_explicit_queue_and_permission_revocation_before_worker_create_no_file(): void
    {
        $n = $this->network();
        $this->pay($n);
        $u = $this->userWithRole('Finance Administrator');
        $e = $this->request($u, extra: ['queue' => true]);
        $u->syncRoles(['Executive Viewer']);
        try {
            app(GenerateReportExportAction::class)->execute($e->id);
            $this->fail('Revoked export must fail.');
        } catch (HttpException $ex) {
            $this->assertSame(403, $ex->getStatusCode());
        }$this->assertSame('failed', $e->refresh()->response_payload['status']);
        $this->assertSame([], Storage::disk('local')->allFiles('report-exports'));
        $this->assertSame(0, MediaAttachment::count());
    }

    public function test_owner_only_downloads_do_not_allow_state_admin_or_another_user_to_read_files(): void
    {
        $u = $this->userWithRole('Finance Administrator');
        $e = $this->request($u);
        foreach (['Finance Administrator', 'Super Administrator'] as $role) {
            $other = $this->userWithRole($role);
            $this->download($other, $e)->assertNotFound();
            $this->actingAs($other)->get('/reports/exports')->assertInertia(fn (Assert $p) => $p->has('records.data', 0));
        }
    }

    public function test_permission_and_pivot_revocation_block_previously_generated_downloads(): void
    {
        $n = $this->network();
        $this->pay($n);
        $u = $this->userWithRole('LGA Administrator');
        $u->lgas()->attach($n['lga'], ['access_level' => 'view', 'created_at' => now()]);
        $e = $this->request($u);
        $this->download($u, $e)->assertOk();
        $u->lgas()->detach();
        $this->download($u, $e)->assertForbidden();
        $v = $this->userWithRole('Finance Administrator');
        $f = $this->request($v);
        $v->syncRoles(['Executive Viewer']);
        $this->download($v, $f)->assertForbidden();
    }

    public function test_geography_change_without_user_grant_change_blocks_registry_snapshot_download(): void
    {
        $a = $this->network();
        $b = $this->network('B');
        $u = $this->userWithRole('LGA Administrator');
        $u->lgas()->attach($a['lga'], ['access_level' => 'view', 'created_at' => now()]);
        $e = $this->request($u, 'drivers');
        $this->download($u, $e)->assertOk();
        $a['park']->update(['lga_id' => $b['lga']->id]);
        $this->download($u, $e)->assertForbidden();
    }

    public function test_file_tampering_and_missing_files_are_denied(): void
    {
        $u = $this->userWithRole('Finance Administrator');
        $e = $this->request($u);
        $m = MediaAttachment::firstOrFail();
        Storage::disk('local')->put($m->path, 'tampered');
        $this->download($u, $e)->assertStatus(410);
        Storage::disk('local')->delete($m->path);
        $this->download($u, $e)->assertStatus(410);
    }

    public function test_replayed_keys_cannot_change_format_actor_or_report_type(): void
    {
        $u = $this->userWithRole('State Administrator');
        $key = bin2hex(random_bytes(32));
        $this->request($u, extra: ['idempotency_key' => $key]);
        foreach ([[$u, 'daily-revenue', ['format' => 'xlsx']], [$u, 'drivers', []], [$this->userWithRole('State Administrator'), 'daily-revenue', []]] as [$actor,$type,$extra]) {
            try {
                $this->request($actor, $type, [...$extra, 'idempotency_key' => $key]);
                $this->fail('Changed request must fail.');
            } catch (ValidationException $ex) {
                $this->assertArrayHasKey('idempotency_key', $ex->errors());
            }
        }$this->assertSame(1, ReportExport::count());
    }

    public function test_failure_cleans_partial_files_retains_safe_status_and_retry_queues_once(): void
    {
        $u = $this->userWithRole('Finance Administrator');
        $this->mock(ReportFileWriter::class)->shouldReceive('write')->once()->andReturnUsing(function ($r, $u, $c, $f, $path) {
            Storage::disk('local')->put($path, 'partial');
            throw new \RuntimeException('PRIVATE-STORAGE-PATH');
        });
        $e = $this->request($u);
        $this->assertSame('failed', $e->response_payload['status']);
        $this->assertStringNotContainsString('PRIVATE-', json_encode(app(ReportExportAccess::class)->dto($u, $e)));
        $this->assertSame([], Storage::disk('local')->allFiles('report-exports'));
        $this->assertSame(0, MediaAttachment::count());
        app(ReportExportService::class)->retry($u, $e);
        app(ReportExportService::class)->retry($u, $e);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertSame('queued', $e->refresh()->response_payload['status']);
    }

    public function test_source_and_pdf_limits_reject_request_without_silent_truncation(): void
    {
        $n = $this->network();
        $this->pay($n);
        $u = $this->userWithRole('State Administrator');
        config(['reports.max_source_rows' => 0]);
        try {
            $this->request($u);
            $this->fail('Oversized export must fail.');
        } catch (ValidationException $ex) {
            $this->assertArrayHasKey('format', $ex->errors());
        }config(['reports.max_source_rows' => 100000, 'reports.pdf_max_rows' => 0]);
        try {
            $this->request($u, 'drivers', ['format' => 'pdf']);
            $this->fail('PDF must fail.');
        } catch (ValidationException $ex) {
            $this->assertArrayHasKey('format', $ex->errors());
        }$this->assertSame(0, ReportExport::count());
        $this->assertSame(0, MediaAttachment::count());
    }

    public function test_queue_failure_rolls_back_request_and_general_audit(): void
    {
        config(['queue.connections.database.connection' => 'different-database']);
        $u = $this->userWithRole('Finance Administrator');
        try {
            $this->request($u, extra: ['queue' => true]);
            $this->fail('Queue must fail.');
        } catch (\LogicException $ex) {
            $this->assertStringContainsString('application database', $ex->getMessage());
        }$this->assertSame(0, ReportExport::count());
        $this->assertSame(0, Activity::where('description', 'report_export_requested')->count());
    }
}
