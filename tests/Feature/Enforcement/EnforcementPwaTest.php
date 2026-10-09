<?php

namespace Tests\Feature\Enforcement;

use App\Domains\Assignments\Models\DriverAssignment;
use App\Domains\Drivers\Models\Driver;
use App\Domains\Enforcement\Actions\RecordInspectionAction;
use App\Domains\Enforcement\Models\Inspection;
use App\Domains\Enforcement\Services\FieldLookupService;
use App\Domains\Enforcement\Services\FieldVerificationService;
use App\Domains\Finance\Actions\ApproveRefundAction;
use App\Domains\Finance\Actions\ProcessRefundAction;
use App\Domains\Finance\Actions\RequestRefundAction;
use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Models\User;
use App\Domains\Operators\Models\Operator;
use App\Domains\Parks\Models\Park;
use App\Domains\Payments\Actions\InitiatePaymentAction;
use App\Domains\Payments\Actions\ReversePaymentAction;
use App\Domains\Revenue\Models\FeeConfiguration;
use App\Domains\Revenue\Models\RevenueHead;
use App\Domains\Routes\Models\Route;
use App\Domains\Ticketing\Actions\IssueTicketAction;
use App\Domains\Ticketing\DTOs\IssueTicketData;
use App\Domains\Ticketing\Models\Ticket;
use App\Domains\Ticketing\Services\TicketIssuanceService;
use App\Domains\Ticketing\Services\TicketVerificationService;
use App\Domains\Vehicles\Models\Vehicle;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\EnforcementDemoSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\FoundationTestCase;

class EnforcementPwaTest extends FoundationTestCase
{
    // Laravel's test URL helper trims trailing slashes; retain the PWA start URL.
    protected function prepareUrlForRequest($uri)
    {
        $url = parent::prepareUrlForRequest($uri);

        return is_string($uri) && str_ends_with($uri, '/field/') ? $url.'/' : $url;
    }

    private function network(string $tag = 'A'): array
    {
        $actor = $this->userWithRole('Ticketing Officer');
        $lga = Lga::create(['code' => $tag, 'name' => 'Synthetic LGA '.$tag, 'status' => 'active']);
        $park = Park::create(['lga_id' => $lga->id, 'park_code' => $tag, 'name' => 'Synthetic Park '.$tag, 'address' => 'Private park address', 'status' => 'active']);
        $route = Route::create(['route_code' => $tag, 'origin' => 'Synthetic origin', 'destination' => 'Synthetic destination', 'status' => 'active']);
        $park->routes()->attach($route, ['status' => 'active', 'created_at' => now()]);
        $operator = Operator::create(['operator_number' => $tag, 'name' => 'Synthetic Operator '.$tag, 'phone' => 'PRIVATE-OP-PHONE', 'status' => 'approved']);
        $operator->parks()->attach($park, ['status' => 'active', 'created_at' => now()]);
        $operator->routes()->attach($route, ['park_id' => $park->id, 'status' => 'active', 'created_at' => now()]);
        $driver = Driver::create(['driver_number' => $tag, 'first_name' => 'PrivateFirst', 'last_name' => 'PrivateLast', 'phone' => 'PRIVATE-DRIVER-PHONE', 'licence_number' => 'PRIVATE-LICENCE', 'residential_address' => 'PRIVATE-RESIDENTIAL', 'licence_expiry' => now()->addYear()->toDateString(), 'status' => 'active']);
        $vehicle = Vehicle::create(['vehicle_number' => $tag, 'registration_number' => 'SYN-'.$tag, 'vehicle_type' => 'bus', 'owner_phone' => 'PRIVATE-OWNER-PHONE', 'roadworthiness_expiry' => now()->addYear()->toDateString(), 'insurance_expiry' => now()->addYear()->toDateString(), 'status' => 'active']);
        $assignment = DriverAssignment::create(['driver_id' => $driver->id, 'vehicle_id' => $vehicle->id, 'operator_id' => $operator->id, 'park_id' => $park->id, 'route_id' => $route->id, 'starts_at' => now()->subHour(), 'is_primary' => true, 'status' => 'active']);
        $actor->parks()->attach($park, ['access_level' => 'manage', 'created_at' => now()]);
        $head = RevenueHead::create(['code' => $tag, 'name' => 'Synthetic daily fee '.$tag, 'frequency' => 'daily', 'status' => 'active']);
        $fee = FeeConfiguration::create(['revenue_head_id' => $head->id, 'amount' => '500.10', 'currency' => 'NGN', 'effective_from' => now()->subDay(), 'priority' => 0, 'status' => 'active']);

        return compact('actor', 'lga', 'park', 'route', 'operator', 'driver', 'vehicle', 'assignment', 'head', 'fee');
    }

    private function data(array $n): array
    {
        $review = app(TicketIssuanceService::class)->review($n['actor'], $n['assignment']->id, $n['head']->id);

        return ['assignment_id' => $n['assignment']->id, 'revenue_head_id' => $n['head']->id, 'confirmation' => $review['confirmation'], 'request_key' => $review['terms']['request_key']];
    }

    private function issue(array $n): Ticket
    {
        return app(IssueTicketAction::class)->execute($n['actor'], IssueTicketData::fromArray($this->data($n)));
    }

    private function officer(array $n): User
    {
        $u = $this->userWithRole('Enforcement Officer');
        $u->parks()->attach($n['park'], ['access_level' => 'view', 'created_at' => now()]);

        return $u;
    }

    private function payload(User $u, array $extra = []): array
    {
        return array_replace(['context' => app(FieldLookupService::class)->contexts($u)->first()['context'], 'inspection_type' => 'vehicle_check', 'result' => 'requires_review', 'notes' => 'Synthetic observation for review.', 'idempotency_key' => bin2hex(random_bytes(32))], $extra);
    }

    private function paid(array $n): array
    {
        config(['ospm.demo_mode' => true, 'ospm.payment_mode' => 'demo', 'ospm.payment_provider' => 'demo']);
        $t = $this->issue($n);
        $finance = $this->userWithRole('Super Administrator');
        $p = app(InitiatePaymentAction::class)->execute($finance, $t, 'successful', bin2hex(random_bytes(32)));

        return [$t->fresh(), $p, $finance];
    }

    public function test_ten_field_screens_render_private_scoped_safe_props(): void
    {
        $n = $this->network();
        $u = $this->officer($n);
        $t = $this->issue($n);
        $this->actingAs($u);
        foreach (['/field/' => 'Home', '/field/scan' => 'Scan', '/field/verify/'.$t->verification_token => 'VerifyResult', '/field/inspections/create' => 'Inspections/Create'] as $path => $screen) {
            $this->get($path)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertInertia(fn (Assert $p) => $p->component('Field/'.$screen));
        }
        foreach (['drivers' => 'driver', 'vehicles' => 'vehicle', 'operators' => 'operator'] as $kind => $key) {
            $this->get('/field/'.$kind.'?search=A')->assertSessionHasErrors('search');
            $this->get('/field/'.$kind.'?search=Synthetic')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Field/'.ucfirst($kind).'/Search'));
            $this->get('/field/'.$kind.'/'.$n[$key]->public_id)->assertOk()->assertInertia(fn (Assert $p) => $p->component('Field/'.ucfirst($kind).'/Show')->missing('record.phone')->missing('record.licence_number')->missing('record.residential_address')->missing('record.owner_phone')->missing('record.id')->missing('record.created_by'));
        }
        $this->get('/field/verify/'.$t->verification_token)->assertInertia(fn (Assert $p) => $p->missing('ticket.context_snapshot')->missing('ticket.verification_token')->missing('ticket.driver')->where('ticket.valid', false)->where('ticket.amount', '500.10'));
    }

    public function test_guests_other_roles_and_finance_routes_fail_closed(): void
    {
        $this->get('/field/')->assertRedirect('/login');
        foreach (['Collection Agent', 'Ticketing Officer', 'Finance Administrator', 'Auditor', 'Transport Operator'] as $role) {
            $this->actingAs($this->userWithRole($role))->get('/field/')->assertForbidden();
        }
        $n = $this->network();
        $u = $this->officer($n);
        $this->actingAs($u);
        foreach (['/finance/ledger', '/finance/refunds', '/finance/settlements', '/finance/dashboard', '/admin/users', '/fee-configurations'] as $path) {
            $this->get($path)->assertForbidden();
        }
        $t = $this->issue($n);
        $this->post('/tickets/'.$t->public_id.'/pay', [])->assertForbidden();
        $this->get('/')->assertRedirect('/field');
    }

    public function test_lookup_excludes_other_parks_and_private_fields(): void
    {
        $a = $this->network('AA');
        $b = $this->network('BB');
        $u = $this->officer($a);
        $this->actingAs($u);
        foreach (['drivers' => 'driver', 'vehicles' => 'vehicle', 'operators' => 'operator'] as $kind => $key) {
            $search = $kind === 'drivers' ? 'Private' : 'Synthetic';
            if ($kind === 'vehicles') {
                $search = 'SYN';
            }
            $this->get('/field/'.$kind.'?search='.$search)->assertInertia(fn (Assert $p) => $p->has('records.data', 1)->where('records.data.0.public_id', $a[$key]->public_id));
            $this->get('/field/'.$kind.'/'.$b[$key]->public_id)->assertForbidden();
            $this->get('/field/'.$kind.'/'.$a[$key]->id)->assertNotFound();
            $response = $this->get('/field/'.$kind.'/'.$a[$key]->public_id);
            $response->assertInertia(fn (Assert $p) => $p->has('assignments.data', 1)->where('assignments.data.0.park.public_id', $a['park']->public_id));
            foreach (['PRIVATE-OP-PHONE', 'PRIVATE-DRIVER-PHONE', 'PRIVATE-LICENCE', 'PRIVATE-RESIDENTIAL', 'PRIVATE-OWNER-PHONE', 'Private park address'] as $secret) {
                $this->assertStringNotContainsString($secret, $response->getContent());
            }
        }
        $this->get('/field/vehicles')->assertInertia(fn (Assert $p) => $p->has('records.data', 0));
        $this->get('/field/vehicles?search=%25%25')->assertInertia(fn (Assert $p) => $p->has('records.data', 0));
    }

    public function test_shared_master_related_history_stays_scoped(): void
    {
        $a = $this->network('AA');
        $b = $this->network('BB');
        $u = $this->officer($a);
        $b['assignment']->update(['driver_id' => $a['driver']->id]);
        $this->actingAs($u)->get('/field/drivers/'.$a['driver']->public_id)->assertInertia(fn (Assert $p) => $p->has('assignments.data', 1)->where('assignments.data.0.park.public_id', $a['park']->public_id));
        $this->get('/field/inspections/create')->assertInertia(fn (Assert $p) => $p->has('contexts', 1)->where('contexts.0.park.public_id', $a['park']->public_id));
    }

    public function test_current_compliance_distinguishes_expired_missing_and_clear(): void
    {
        $n = $this->network();
        $u = $this->officer($n);
        $this->actingAs($u);
        $url = '/field/drivers/'.$n['driver']->public_id;
        $this->get($url)->assertInertia(fn (Assert $p) => $p->where('compliance.status', 'clear'));
        $n['driver']->update(['licence_expiry' => null]);
        $this->get($url)->assertInertia(fn (Assert $p) => $p->where('compliance.status', 'requires_review'));
        $n['driver']->update(['licence_expiry' => now()->subDay()->toDateString()]);
        $this->get($url)->assertInertia(fn (Assert $p) => $p->where('compliance.status', 'attention'));
        $n['driver']->update(['licence_expiry' => now()->toDateString()]);
        $this->get($url)->assertInertia(fn (Assert $p) => $p->where('compliance.status', 'clear'));
    }

    public function test_verification_is_scoped_strict_and_matches_safe_services(): void
    {
        $a = $this->network('AA');
        $b = $this->network('BB');
        $u = $this->officer($a);
        [$t,$payment] = $this->paid($a);
        $other = $this->issue($b);
        $this->actingAs($u);
        $this->get('/field/verify/'.$t->verification_token)->assertInertia(fn (Assert $p) => $p->where('ticket.valid', true));
        $this->get('/field/verify/'.$payment->receipt->verification_token.'?kind=receipt')->assertInertia(fn (Assert $p) => $p->where('receipt.valid', true)->missing('receipt.context')->missing('receipt.payment_public_id'));
        $this->assertEquals(app(TicketVerificationService::class)->safe($t), app(FieldVerificationService::class)->verify($u, $t->verification_token, 'ticket')['ticket']);
        foreach ([$other->verification_token, str_repeat('0', 64), 'invalid', strtoupper($t->verification_token)] as $token) {
            $this->get('/field/verify/'.$token)->assertNotFound();
        }
        $this->get('/field/verify/'.$t->verification_token.'?kind=driver')->assertSessionHasErrors('kind');
    }

    public function test_expired_and_reversed_results_are_never_approved_as_valid(): void
    {
        $n = $this->network();
        $u = $this->officer($n);
        [$t,$p,$finance] = $this->paid($n);
        DB::table('tickets')->where('id', $t->id)->update(['expires_at' => now()->subSecond()]);
        $this->actingAs($u)->get('/field/verify/'.$t->verification_token)->assertInertia(fn (Assert $page) => $page->where('ticket.valid', false)->where('ticket.ticket_status', 'expired'));
        app(ReversePaymentAction::class)->execute($finance, $p, 'Synthetic reversal evidence.', bin2hex(random_bytes(32)));
        $this->get('/field/verify/'.$p->receipt->verification_token.'?kind=receipt')->assertInertia(fn (Assert $page) => $page->where('receipt.valid', false));
    }

    public function test_partial_refunds_invalidate_both_field_verdicts_and_retain_original_amounts(): void
    {
        $n = $this->network();
        $u = $this->officer($n);
        [$t,$p,$finance] = $this->paid($n);
        $approver = $this->userWithRole('Finance Administrator');
        $r = app(RequestRefundAction::class)->execute($finance, $p, ['amount' => '0.10', 'reason' => 'Synthetic partial refund evidence.', 'idempotency_key' => bin2hex(random_bytes(32))]);
        app(ApproveRefundAction::class)->execute($approver, $r, 'approved', 'Independent synthetic review.', bin2hex(random_bytes(32)));
        app(ProcessRefundAction::class)->execute($approver, $r, 'successful', 'Synthetic processing.', bin2hex(random_bytes(32)));
        $this->actingAs($u)->get('/field/verify/'.$t->verification_token)->assertInertia(fn (Assert $page) => $page->where('ticket.valid', false)->where('ticket.amount', '500.10'));
        $this->get('/field/verify/'.$p->receipt->verification_token.'?kind=receipt')->assertInertia(fn (Assert $page) => $page->where('receipt.valid', false)->where('receipt.amount', '500.10'));
    }

    public function test_inspection_derives_all_foreign_keys_actor_and_times_on_server(): void
    {
        $n = $this->network();
        $u = $this->officer($n);
        $data = $this->payload($u, ['latitude' => '7.7654321', 'longitude' => '4.1234567']);
        $this->actingAs($u)->post('/field/inspections', $data + ['officer_user_id' => 999, 'park_id' => 999, 'driver_id' => 999, 'inspection_reference' => 'FORGED', 'occurred_at' => '2099-01-01'])->assertSessionHasNoErrors()->assertHeader('Location', '/field/');
        $r = Inspection::firstOrFail();
        $this->assertSame($u->id, $r->officer_user_id);
        foreach (['park', 'operator', 'driver', 'vehicle'] as $key) {
            $this->assertSame($n[$key]->id, $r->{$key.'_id'});
        }
        $this->assertSame('7.7654321', $r->latitude);
        $this->assertSame('4.1234567', $r->longitude);
        $this->assertNull($r->ticket_id);
        $this->assertTrue($r->occurred_at->isToday());
        $this->assertStringStartsWith('OSPM-INSP-', $r->inspection_reference);
        $this->get('/field/')->assertInertia(fn (Assert $p) => $p->where('flash.success', 'Inspection '.$r->inspection_reference.' recorded.'));
        $this->assertDatabaseHas('activity_log', ['description' => 'inspection_recorded', 'causer_id' => $u->id, 'subject_id' => $r->id]);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_confirmed_retry_creates_one_inspection_and_one_audit(): void
    {
        $n = $this->network();
        $u = $this->officer($n);
        $data = $this->payload($u);
        $this->actingAs($u);
        $this->post('/field/inspections', $data)->assertSessionHasNoErrors();
        $this->post('/field/inspections', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('inspections', 1);
        $this->assertSame(1, DB::table('activity_log')->where('description', 'inspection_recorded')->count());
        $this->post('/field/inspections', array_replace($data, ['notes' => 'Different observation content.']))->assertSessionHasErrors('idempotency_key');
        $other = $this->officer($n);
        $this->actingAs($other)->post('/field/inspections', $data)->assertSessionHasErrors('idempotency_key');
    }

    public function test_permission_revocation_blocks_confirmation_replay(): void
    {
        $n = $this->network();
        $u = $this->officer($n);
        $data = $this->payload($u);
        app(RecordInspectionAction::class)->execute($u, $data);
        $u->removeRole('Enforcement Officer');
        $this->expectException(AuthorizationException::class);
        app(RecordInspectionAction::class)->execute($u, $data);
    }

    public function test_mixed_context_and_out_of_scope_ticket_cannot_be_recorded(): void
    {
        $a = $this->network('AA');
        $b = $this->network('BB');
        $u = $this->officer($a);
        $other = $this->issue($b);
        $this->actingAs($u);
        $context = implode(':', [$a['driver']->public_id, $b['vehicle']->public_id, $a['operator']->public_id, $a['park']->public_id]);
        $this->post('/field/inspections', $this->payload($u, ['context' => $context]))->assertSessionHasErrors('context');
        $this->post('/field/inspections', $this->payload($u, ['ticket' => $other->public_id, 'inspection_type' => 'ticket_verification']))->assertSessionHasErrors('context');
        $this->assertDatabaseCount('inspections', 0);
    }

    public function test_ended_future_and_deleted_assignment_contexts_reject_stale_forms(): void
    {
        $n = $this->network();
        $u = $this->officer($n);
        $data = $this->payload($u);
        $this->actingAs($u);
        $n['assignment']->update(['ends_at' => now()->subMinute()]);
        $this->post('/field/inspections', $data)->assertSessionHasErrors('context');
        $n['assignment']->update(['ends_at' => null, 'starts_at' => now()->addHour()]);
        $this->post('/field/inspections', $data)->assertSessionHasErrors('context');
        $n['assignment']->update(['starts_at' => now()->subHour()]);
        $n['vehicle']->delete();
        $this->post('/field/inspections', $data)->assertSessionHasErrors('context');
        $this->assertDatabaseCount('inspections', 0);
    }

    public function test_missing_or_expired_record_checks_cannot_be_marked_clear(): void
    {
        $n = $this->network();
        $u = $this->officer($n);
        $data = $this->payload($u, ['result' => 'compliant']);
        $this->actingAs($u);
        foreach (['licence_expiry' => null, 'licence_expiry_expired' => now()->subDay()->toDateString()] as $value) {
            $n['driver']->update(['licence_expiry' => $value]);
            $this->post('/field/inspections', $data)->assertSessionHasErrors('result');
        }
        $n['driver']->update(['licence_expiry' => now()->addYear()->toDateString()]);
        $this->post('/field/inspections', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('inspections', 1);
    }

    public function test_ticket_inspection_requires_matching_type_and_validity_for_clear_result(): void
    {
        $n = $this->network();
        $u = $this->officer($n);
        $t = $this->issue($n);
        $data = $this->payload($u, ['ticket' => $t->public_id, 'result' => 'compliant', 'inspection_type' => 'ticket_verification']);
        $this->actingAs($u);
        $this->post('/field/inspections', $data)->assertSessionHasErrors('result');
        $this->post('/field/inspections', array_replace($data, ['result' => 'requires_review', 'inspection_type' => 'driver_check']))->assertSessionHasErrors('inspection_type');
        $this->post('/field/inspections', array_replace($data, ['result' => 'requires_review']))->assertSessionHasNoErrors();
        $this->assertSame($t->id, Inspection::firstOrFail()->ticket_id);
    }

    public function test_coordinates_notes_and_enum_validation_prevent_partial_writes(): void
    {
        $n = $this->network();
        $u = $this->officer($n);
        $data = $this->payload($u);
        $this->actingAs($u);
        foreach ([['latitude' => '91', 'longitude' => '4'], ['latitude' => '7', 'longitude' => '181'], ['latitude' => '7.12345678', 'longitude' => '4'], ['latitude' => '7'], ['longitude' => '4'], ['result' => 'invented'], ['notes' => '   '], ['inspection_type' => 'violation']] as $bad) {
            $this->post('/field/inspections', array_replace($data, $bad))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('inspections', 0);
        $this->assertDatabaseCount('idempotency_keys', 0);
    }

    public function test_audit_failure_rolls_back_inspection_and_confirmation(): void
    {
        $n = $this->network();
        $u = $this->officer($n);
        $data = $this->payload($u);
        Activity::creating(function ($r) {
            if ($r->description === 'inspection_recorded') {
                throw new \RuntimeException('Synthetic audit failure');
            }
        });
        try {
            app(RecordInspectionAction::class)->execute($u, $data);
            $this->fail('Expected audit failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic audit failure', $e->getMessage());
        } finally {
            Activity::flushEventListeners();
        }
        $this->assertDatabaseCount('inspections', 0);
        $this->assertDatabaseCount('idempotency_keys', 0);
    }

    public function test_inspection_is_immutable_and_schema_matches_approved_columns(): void
    {
        $n = $this->network();
        $u = $this->officer($n);
        $r = app(RecordInspectionAction::class)->execute($u, $this->payload($u));
        foreach (['update', 'delete'] as $operation) {
            try {
                $operation === 'update' ? $r->update(['notes' => 'Forged replacement']) : $r->delete();
                $this->fail('Expected retention guard');
            } catch (\LogicException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $this->assertSame(['id', 'public_id', 'inspection_reference', 'officer_user_id', 'park_id', 'operator_id', 'driver_id', 'vehicle_id', 'ticket_id', 'inspection_type', 'result', 'notes', 'latitude', 'longitude', 'occurred_at', 'created_at'], Schema::getColumnListing('inspections'));
        $this->assertCount(6, Schema::getForeignKeys('inspections'));
        $this->assertDatabaseCount('inspections', 1);
    }

    public function test_seed_and_reset_retain_inspections_and_financial_history(): void
    {
        config(['ospm.demo_mode' => true, 'ospm.payment_mode' => 'demo', 'ospm.payment_provider' => 'demo', 'ospm.demo_password' => 'SyntheticTest123!']);
        $this->seed(DatabaseSeeder::class);
        $before = Inspection::firstOrFail()->toArray();
        $count = Inspection::count();
        $payments = DB::table('payments')->count();
        $ledger = DB::table('financial_transactions')->count();
        $this->seed(EnforcementDemoSeeder::class);
        $this->artisan('ospm:demo-reset')->assertSuccessful();
        $this->assertSame($before, Inspection::firstOrFail()->toArray());
        $this->assertSame($count, Inspection::count());
        $this->assertSame($payments, DB::table('payments')->count());
        $this->assertSame($ledger, DB::table('financial_transactions')->count());
    }

    public function test_revoked_geographical_scope_blocks_new_writes_and_replays(): void
    {
        $n = $this->network();
        $u = $this->officer($n);
        $data = $this->payload($u);
        app(RecordInspectionAction::class)->execute($u, $data);
        $u->parks()->detach();
        $u->unsetRelation('parks');
        $this->actingAs($u)->post('/field/inspections', $data)->assertSessionHasErrors('context');
        $this->get('/field/vehicles?search=SYN')->assertInertia(fn (Assert $p) => $p->has('records.data', 0));
        $this->assertDatabaseCount('inspections', 1);
    }

    public function test_confirmation_replay_retains_committed_result_after_assignment_ends(): void
    {
        $n = $this->network();
        $u = $this->officer($n);
        $data = $this->payload($u);
        $r = app(RecordInspectionAction::class)->execute($u, $data);
        $n['assignment']->update(['status' => 'ended', 'ends_at' => now()]);
        $this->assertSame($r->id, app(RecordInspectionAction::class)->execute($u, $data)->id);
        $this->assertDatabaseCount('inspections', 1);
    }

    public function test_active_user_and_password_change_middleware_apply_to_field(): void
    {
        $n = $this->network();
        $u = $this->officer($n);
        $u->update(['must_change_password' => true]);
        $this->actingAs($u)->get('/field/')->assertRedirect(route('account.security'));
        $u->update(['must_change_password' => false, 'status' => 'inactive']);
        $this->get('/field/')->assertRedirect('/login');
    }

    public function test_field_home_canonical_url_and_anonymous_worker_do_not_require_officer_session(): void
    {
        $this->get('/field/sw.js')->assertOk()->assertHeader('Service-Worker-Allowed', '/field/')->assertHeader('Content-Type', 'text/javascript; charset=UTF-8');
        $n = $this->network();
        $this->actingAs($this->officer($n))->get('/field')->assertRedirect('/field/');
        $this->get('/field/')->assertOk();
    }

    public function test_actor_rate_limits_apply_to_verification_lookup_and_inspections(): void
    {
        $n = $this->network();
        $u = $this->officer($n);
        $this->actingAs($u);
        for ($i = 0; $i < 60; $i++) {
            $this->get('/field/verify/'.str_repeat('0', 64))->assertNotFound();
        }
        $this->get('/field/verify/'.str_repeat('0', 64))->assertStatus(429);
        for ($i = 0; $i < 60; $i++) {
            $this->get('/field/vehicles')->assertOk();
        }
        $this->get('/field/drivers')->assertStatus(429);
        for ($i = 0; $i < 30; $i++) {
            $this->post('/field/inspections', [])->assertSessionHasErrors();
        }
        $this->post('/field/inspections', [])->assertStatus(429);
        $this->assertDatabaseCount('inspections', 0);
    }

    public function test_valid_paid_ticket_with_ended_assignment_cannot_be_marked_clear(): void
    {
        $n = $this->network();
        $u = $this->officer($n);
        [$t] = $this->paid($n);
        $data = $this->payload($u, ['ticket' => $t->public_id, 'inspection_type' => 'ticket_verification', 'result' => 'compliant']);
        $n['assignment']->update(['status' => 'ended', 'ends_at' => now()]);
        $this->actingAs($u)->post('/field/inspections', $data)->assertSessionHasErrors('result');
        $this->assertDatabaseCount('inspections', 0);
    }

    public function test_logout_and_inactive_revocation_clear_encrypted_field_history(): void
    {
        $n = $this->network();
        $u = $this->officer($n);
        $this->assertTrue($this->actingAs($u)->get('/field/')->viewData('page')['encryptHistory']);
        $this->post('/logout')->assertRedirect('/login')->assertSessionHas('inertia.clear_history', true);
        $page = $this->get('/login')->assertOk()->viewData('page');
        $this->assertTrue($page['clearHistory']);
        $this->assertNull($page['props']['auth']['user']);
        $this->assertFalse($this->get('/login')->viewData('page')['clearHistory']);
        $u->update(['status' => 'inactive']);
        $this->actingAs($u)->get('/field/')->assertRedirect('/login')->assertSessionHas('inertia.clear_history', true);
        $page = $this->get('/login')->assertOk()->viewData('page');
        $this->assertTrue($page['clearHistory']);
        $this->assertNull($page['props']['auth']['user']);
    }

    public function test_administration_keeps_ticket_inspections_in_original_lga_after_park_moves(): void
    {
        $n = $this->network();
        $other = $this->network('B');
        $ticket = $this->issue($n);
        $officer = $this->officer($n);
        $inspection = app(RecordInspectionAction::class)->execute($officer, ['ticket' => $ticket->public_id, 'inspection_type' => 'ticket_verification', 'result' => 'requires_review', 'notes' => 'Synthetic historical observation requiring review.', 'idempotency_key' => bin2hex(random_bytes(32))]);
        $viewer = $this->userWithRole('LGA Administrator');
        $viewer->lgas()->attach($n['lga'], ['access_level' => 'manage', 'created_at' => now()]);
        $otherViewer = $this->userWithRole('LGA Administrator');
        $otherViewer->lgas()->attach($other['lga'], ['access_level' => 'manage', 'created_at' => now()]);
        $n['park']->update(['lga_id' => $other['lga']->id]);
        $this->actingAs($viewer)->get('/inspections/'.$inspection->public_id)->assertOk();
        $this->actingAs($otherViewer)->get('/inspections/'.$inspection->public_id)->assertForbidden();
        $this->actingAs($viewer)->get('/inspections')->assertInertia(fn (Assert $p) => $p->has('records.data', 1));
    }
}
