<?php

namespace Tests\Feature\Operations;

use App\Domains\Assignments\Actions\CreateAssignmentAction;
use App\Domains\Assignments\Actions\EndAssignmentAction;
use App\Domains\Assignments\Models\DriverAssignment;
use App\Domains\Drivers\Models\Driver;
use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Models\User;
use App\Domains\Operators\Actions\SaveOperatorAction;
use App\Domains\Operators\Models\Operator;
use App\Domains\Parks\Models\Park;
use App\Domains\Revenue\Actions\SaveFeeConfigurationAction;
use App\Domains\Revenue\Models\FeeConfiguration;
use App\Domains\Revenue\Models\RevenueHead;
use App\Domains\Routes\Models\Route;
use App\Domains\System\Models\MediaAttachment;
use App\Domains\Vehicles\Models\Vehicle;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TransportRevenueDemoSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\FoundationTestCase;

class TransportRevenueTest extends FoundationTestCase
{
    private function network(): array
    {
        $state = $this->userWithRole('State Administrator');
        $a = Lga::create(['code' => 'A', 'name' => 'Synthetic LGA A', 'status' => 'active']);
        $b = Lga::create(['code' => 'B', 'name' => 'Synthetic LGA B', 'status' => 'active']);
        $pa = Park::create(['lga_id' => $a->id, 'park_code' => 'PA', 'name' => 'Synthetic Park A', 'address' => 'Synthetic', 'status' => 'active']);
        $pb = Park::create(['lga_id' => $b->id, 'park_code' => 'PB', 'name' => 'Synthetic Park B', 'address' => 'Synthetic', 'status' => 'active']);
        $route = Route::create(['route_code' => 'R', 'origin' => 'Demo A', 'destination' => 'Demo B', 'status' => 'active']);
        foreach ([$pa, $pb] as $park) {
            $park->routes()->attach($route, ['status' => 'active', 'created_at' => now()]);
        }
        $oa = app(SaveOperatorAction::class)->execute($state, ['name' => 'Synthetic Operator A', 'status' => 'approved', 'park_ids' => [$pa->id], 'route_keys' => [$pa->id.':'.$route->id]]);
        $ob = app(SaveOperatorAction::class)->execute($state, ['name' => 'Synthetic Operator B', 'status' => 'approved', 'park_ids' => [$pb->id], 'route_keys' => [$pb->id.':'.$route->id]]);
        $driver = Driver::create(['driver_number' => 'DRV', 'first_name' => 'Synthetic', 'last_name' => 'Driver', 'phone' => '00000000000', 'status' => 'active', 'created_by' => $state->id]);
        $vehicle = Vehicle::create(['vehicle_number' => 'VEH', 'registration_number' => 'DEMO-123', 'vehicle_type' => 'bus', 'status' => 'active', 'created_by' => $state->id]);
        $data = ['driver_id' => $driver->id, 'vehicle_id' => $vehicle->id, 'operator_id' => $oa->id, 'park_id' => $pa->id, 'route_id' => $route->id, 'starts_at' => now()->subHour()->toDateTimeString(), 'is_primary' => true];
        $assignment = app(CreateAssignmentAction::class)->execute($state, $data);

        return compact('state', 'a', 'b', 'pa', 'pb', 'route', 'oa', 'ob', 'driver', 'vehicle', 'data', 'assignment');
    }

    private function feeData(RevenueHead $head, array $extra = []): array
    {
        return array_replace(['revenue_head_id' => $head->id, 'amount' => '500.00', 'currency' => 'NGN', 'vehicle_type' => null, 'lga_id' => null, 'park_id' => null, 'route_id' => null, 'priority' => 0, 'effective_from' => now()->addDay()->toDateTimeString(), 'effective_to' => null, 'status' => 'active'], $extra);
    }

    public function test_all_twenty_four_screens_render_and_business_references_are_server_generated(): void
    {
        extract($this->network());
        $admin = $this->userWithRole('Super Administrator');
        $this->actingAs($admin);
        $this->post('/operators', ['name' => 'New demo operator', 'status' => 'pending', 'park_ids' => [$pa->id], 'route_keys' => [], 'operator_number' => 'FORGED'])->assertSessionHasNoErrors();
        $this->post('/drivers', ['first_name' => 'New', 'last_name' => 'Driver', 'phone' => '00000000000', 'status' => 'pending'])->assertSessionHasNoErrors();
        $this->post('/vehicles', ['registration_number' => 'demo-new', 'vehicle_type' => 'taxi', 'status' => 'pending'])->assertSessionHasNoErrors();
        $this->post('/revenue-heads', ['code' => 'DPT', 'name' => 'Synthetic fee', 'frequency' => 'daily', 'status' => 'active'])->assertSessionHasNoErrors();
        $head = RevenueHead::firstOrFail();
        $this->post('/fee-configurations', $this->feeData($head))->assertSessionHasNoErrors();
        foreach (['operators' => Operator::latest('id')->first(), 'drivers' => Driver::latest('id')->first(), 'vehicles' => Vehicle::latest('id')->first(), 'revenue-heads' => $head, 'fee-configurations' => FeeConfiguration::first()] as $kind => $r) {
            $page = ['revenue-heads' => 'RevenueHeads', 'fee-configurations' => 'Fees'][$kind] ?? ucfirst($kind);
            foreach (['' => 'Index', '/create' => 'Create', '/'.$r->public_id => 'Show', '/'.$r->public_id.'/edit' => 'Edit'] as $suffix => $screen) {
                $this->get('/'.$kind.$suffix)->assertOk()->assertInertia(fn (Assert $p) => $p->component($page.'/'.$screen));
            }
            $this->get('/'.$kind.'/'.$r->id)->assertNotFound();
        }
        foreach (['' => 'Index', '/create' => 'Create', '/'.$assignment->id => 'Show', '/'.$assignment->id.'/end' => 'End'] as $suffix => $screen) {
            $this->get('/assignments'.$suffix)->assertOk()->assertInertia(fn (Assert $p) => $p->component('Assignments/'.$screen));
        }
        $this->assertStringStartsWith('OSPM-OPR-', Operator::latest('id')->first()->operator_number);
        $this->assertSame('DEMO-NEW', Vehicle::latest('id')->first()->registration_number);
    }

    public function test_operator_account_cannot_cross_private_profiles_documents_or_assignments(): void
    {
        extract($this->network());
        $u = $this->userWithRole('Transport Operator');
        $u->operators()->attach($oa, ['access_level' => 'view', 'created_at' => now()]);
        $this->actingAs($u);
        $this->get('/operators')->assertInertia(fn (Assert $p) => $p->has('records.data', 1)->where('records.data.0.public_id', $oa->public_id));
        $this->get('/operators/'.$oa->public_id)->assertOk()->assertInertia(fn (Assert $p) => $p->has('related.assignments.data', 1)->where('can_update', false));
        $this->get('/operators/'.$ob->public_id)->assertForbidden();
        $this->get('/operators/'.$oa->public_id.'/edit')->assertForbidden();
        $this->get('/drivers/'.$driver->public_id)->assertOk();
        $this->get('/assignments')->assertInertia(fn (Assert $p) => $p->has('records.data', 1));
        $this->get('/fee-configurations')->assertForbidden();
    }

    public function test_lga_scopes_filter_operator_children_and_shared_master_writes_fail_closed(): void
    {
        extract($this->network());
        $u = $this->userWithRole('LGA Administrator');
        $u->lgas()->attach($a, ['access_level' => 'manage', 'created_at' => now()]);
        $this->actingAs($u);
        $this->get('/operators?search=Synthetic')->assertInertia(fn (Assert $p) => $p->has('records.data', 1));
        $this->get('/operators/'.$ob->public_id)->assertForbidden();
        $this->get('/drivers')->assertInertia(fn (Assert $p) => $p->has('records.data', 1));
        $oa->parks()->attach($pb, ['status' => 'active', 'created_at' => now()]);
        $this->get('/operators/'.$oa->public_id)->assertInertia(fn (Assert $p) => $p->has('related.parks.data', 1)->where('can_update', false));
        $this->patch('/operators/'.$oa->public_id, ['name' => 'Changed', 'status' => 'approved', 'park_ids' => [$pa->id], 'route_keys' => []])->assertForbidden();
        $this->post('/operators', ['name' => 'Forbidden geography', 'status' => 'pending', 'park_ids' => [$pb->id], 'route_keys' => []])->assertForbidden();
    }

    public function test_view_scope_and_auditor_never_grant_write_or_approval(): void
    {
        extract($this->network());
        $u = $this->userWithRole('Park Manager');
        $u->parks()->attach($pa, ['access_level' => 'view', 'created_at' => now()]);
        $this->actingAs($u)->get('/operators/'.$oa->public_id.'/edit')->assertForbidden();
        $u->parks()->updateExistingPivot($pa->id, ['access_level' => 'manage']);
        $this->patch('/operators/'.$oa->public_id, ['name' => $oa->name, 'status' => 'suspended', 'park_ids' => [$pa->id], 'route_keys' => []])->assertForbidden();
        $auditor = $this->userWithRole('Auditor');
        $this->actingAs($auditor)->get('/operators/'.$oa->public_id.'/edit')->assertForbidden();
        $this->post('/drivers', ['first_name' => 'Bad', 'last_name' => 'Write', 'phone' => '000', 'status' => 'pending'])->assertForbidden();
    }

    public function test_operator_link_removal_retains_history_and_reactivation_does_not_duplicate(): void
    {
        extract($this->network());
        $this->actingAs($state);
        $link = DB::table('operator_route')->where('operator_id', $oa->id)->first();
        $base = ['name' => $oa->name, 'status' => 'approved', 'park_ids' => [$pa->id], 'route_keys' => []];
        $this->patch('/operators/'.$oa->public_id, $base)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('operator_route', ['id' => $link->id, 'status' => 'inactive', 'created_at' => $link->created_at]);
        $this->patch('/operators/'.$oa->public_id, array_replace($base, ['route_keys' => [$pa->id.':'.$route->id]]))->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('operator_route')->where('operator_id', $oa->id)->count());
    }

    public function test_one_primary_assignment_end_then_reassign_preserves_history_and_actor(): void
    {
        extract($this->network());
        $this->actingAs($state);
        $this->post('/assignments', $data)->assertSessionHasErrors('driver_id');
        $this->post('/assignments', array_replace($data, ['is_primary' => false]))->assertSessionHasNoErrors();
        $this->patch('/assignments/'.$assignment->id.'/end', ['ends_at' => now()->subMinutes(5)->toDateTimeString()])->assertSessionHasNoErrors();
        $this->post('/assignments', array_replace($data, ['starts_at' => now()->toDateTimeString(), 'assigned_by' => 999]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('driver_assignments', ['id' => $assignment->id, 'status' => 'ended']);
        $this->assertSame(3, DriverAssignment::count());
        $this->assertSame($state->id, DriverAssignment::latest('id')->first()->assigned_by);
        $this->assertDatabaseHas('activity_log', ['description' => 'assignment_ended', 'causer_id' => $state->id]);
        $this->patch('/assignments/'.$assignment->id.'/end', ['ends_at' => now()->toDateTimeString()])->assertSessionHasErrors('ends_at');
    }

    public function test_assignment_rejects_inactive_participant_wrong_park_route_and_foreign_scope(): void
    {
        extract($this->network());
        app(EndAssignmentAction::class)->execute($state, $assignment, now()->toDateTimeString());
        $this->actingAs($state);
        $driver->update(['status' => 'suspended']);
        $this->post('/assignments', $data)->assertSessionHasErrors('driver_id');
        $driver->update(['status' => 'active']);
        $this->post('/assignments', array_replace($data, ['operator_id' => $ob->id]))->assertSessionHasErrors('operator_id');
        DB::table('operator_route')->where('operator_id', $oa->id)->update(['status' => 'inactive']);
        $this->post('/assignments', $data)->assertSessionHasErrors('route_id');
        $local = $this->userWithRole('Park Manager');
        $local->parks()->attach($pb, ['access_level' => 'manage', 'created_at' => now()]);
        $this->actingAs($local)->post('/assignments', $data)->assertForbidden();
    }

    public function test_document_validation_private_storage_and_parent_binding(): void
    {
        extract($this->network());
        Storage::fake('local');
        $this->actingAs($state);
        $this->post('/drivers/'.$driver->public_id.'/documents', ['category' => 'driver_photo', 'file' => UploadedFile::fake()->image('photo.jpg')])->assertSessionHasNoErrors();
        $media = MediaAttachment::firstOrFail();
        Storage::disk('local')->assertExists($media->path);
        $this->assertNotContains('path', array_keys($media->toArray()));
        $this->get('/drivers/'.$driver->public_id.'/documents/'.$media->public_id)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/operators/'.$oa->public_id.'/documents/'.$media->public_id)->assertNotFound();
        $this->post('/drivers/'.$driver->public_id.'/documents', ['category' => 'driver_photo', 'file' => UploadedFile::fake()->create('script.php', 2, 'application/x-httpd-php')])->assertSessionHasErrors('file');
        $this->post('/drivers/'.$driver->public_id.'/documents', ['category' => 'driver_photo', 'file' => UploadedFile::fake()->image('photo.jpg')->size(5121)])->assertSessionHasErrors('file');
        $this->post('/drivers/'.$driver->public_id.'/documents', ['category' => 'incident_evidence', 'file' => UploadedFile::fake()->image('photo.jpg')])->assertSessionHasErrors('category');
        $local = $this->userWithRole('Park Manager');
        $local->parks()->attach($pb, ['access_level' => 'manage', 'created_at' => now()]);
        $this->actingAs($local)->get('/drivers/'.$driver->public_id.'/documents/'.$media->public_id)->assertForbidden();
    }

    public function test_fee_terms_become_immutable_at_effective_time_and_future_changes_preserve_history(): void
    {
        $finance = $this->userWithRole('Finance Administrator');
        $this->actingAs($finance);
        $this->post('/revenue-heads', ['code' => 'FEE', 'name' => 'Demo fee', 'frequency' => 'daily', 'status' => 'active'])->assertSessionHasNoErrors();
        $head = RevenueHead::firstOrFail();
        $data = $this->feeData($head);
        $this->post('/fee-configurations', $data)->assertSessionHasNoErrors();
        $fee = FeeConfiguration::firstOrFail();
        $this->patch('/fee-configurations/'.$fee->public_id, array_replace($data, ['amount' => '700.10']))->assertSessionHasNoErrors();
        $this->travel(2)->days();
        $data['amount'] = '700.10';
        $this->patch('/fee-configurations/'.$fee->public_id, array_replace($data, ['amount' => '900.00']))->assertSessionHasErrors('amount');
        $this->patch('/fee-configurations/'.$fee->public_id, array_replace($data, ['status' => 'inactive']))->assertSessionHasNoErrors();
        $this->assertSame('700.10', $fee->fresh()->amount);
        $this->patch('/fee-configurations/'.$fee->public_id, $data)->assertSessionHasErrors('amount');
        $this->travelBack();
    }

    public function test_finance_management_is_denied_to_state_collectors_and_auditors_and_money_is_exact(): void
    {
        $head = RevenueHead::create(['code' => 'F', 'name' => 'Synthetic', 'frequency' => 'daily', 'status' => 'active']);
        foreach (['State Administrator', 'Collection Agent', 'Auditor'] as $role) {
            $this->actingAs($this->userWithRole($role))->post('/fee-configurations', $this->feeData($head))->assertForbidden();
        }
        $this->actingAs($this->userWithRole('Finance Administrator'));
        foreach (['1.234', '-1.00', '1e3', '10000000000000.00'] as $amount) {
            $this->post('/fee-configurations', $this->feeData($head, ['amount' => $amount]))->assertSessionHasErrors('amount');
        }
        $amount = DB::getDriverName() === 'sqlite' ? '500.10' : '9999999999999.99';
        $this->post('/fee-configurations', $this->feeData($head, ['amount' => $amount]))->assertSessionHasNoErrors();
        $this->assertSame($amount, FeeConfiguration::first()->amount);
    }

    public function test_approval_suspension_and_private_photo_preview_are_audited(): void
    {
        extract($this->network());
        $this->actingAs($state);
        $base = ['first_name' => $driver->first_name, 'last_name' => $driver->last_name, 'phone' => $driver->phone, 'status' => 'suspended'];
        $this->patch('/drivers/'.$driver->public_id, $base)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('activity_log', ['description' => 'driver_suspended', 'causer_id' => $state->id]);
        $this->patch('/drivers/'.$driver->public_id, array_replace($base, ['status' => 'active', 'approved_by' => 999]))->assertSessionHasNoErrors();
        $this->assertSame($state->id, $driver->fresh()->approved_by);
        $this->patch('/vehicles/'.$vehicle->public_id, ['registration_number' => $vehicle->registration_number, 'vehicle_type' => 'bus', 'status' => 'suspended'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('activity_log', ['description' => 'vehicle_suspended']);
        Storage::fake('local');
        $this->post('/drivers/'.$driver->public_id.'/documents', ['category' => 'driver_photo', 'file' => UploadedFile::fake()->image('photo.jpg')])->assertSessionHasNoErrors();
        $media = MediaAttachment::first();
        $this->get('/drivers/'.$driver->public_id.'/documents/'.$media->public_id.'?preview=1')->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_server_search_filters_pagination_and_parent_connections_do_not_leak(): void
    {
        extract($this->network());
        $this->actingAs($state);
        for ($i = 0; $i < 17; $i++) {
            Driver::create(['driver_number' => 'SEARCH-'.$i, 'first_name' => 'Search', 'last_name' => 'Synthetic '.$i, 'phone' => '00000000000', 'status' => 'pending', 'created_by' => $state->id]);
        }
        $this->get('/drivers?search=Search&status=pending&sort=first_name&direction=asc')->assertInertia(fn (Assert $p) => $p->has('records.data', 15)->where('records.total', 17));
        $this->get('/vehicles?search=DEMO-123&vehicle_type=bus')->assertInertia(fn (Assert $p) => $p->has('records.data', 1));
        $this->get('/assignments?search=DEMO-123&status=active')->assertInertia(fn (Assert $p) => $p->has('records.data', 1));
        $local = $this->userWithRole('LGA Administrator');
        $local->lgas()->attach($a, ['access_level' => 'manage', 'created_at' => now()]);
        $this->actingAs($local);
        $this->get('/operators?search=Synthetic&park_id='.$pb->id)->assertInertia(fn (Assert $p) => $p->has('records.data', 0));
        $this->get('/parks/'.$pa->public_id)->assertInertia(fn (Assert $p) => $p->has('transport.operators.data', 1)->has('transport.drivers.data', 1)->has('transport.vehicles.data', 1));
        $this->get('/parks/'.$pa->public_id.'/dashboard')->assertInertia(fn (Assert $p) => $p->where('metrics.1.value', 1)->where('metrics.2.value', 1)->where('metrics.3.value', 1));
    }

    public function test_stale_action_inputs_cannot_overwrite_effective_fees_or_bypass_authorization(): void
    {
        extract($this->network());
        $finance = $this->userWithRole('Finance Administrator');
        $head = RevenueHead::create(['code' => 'STALE', 'name' => 'Synthetic stale test', 'frequency' => 'daily', 'status' => 'active']);
        $data = $this->feeData($head);
        $action = app(SaveFeeConfigurationAction::class);
        $fee = $action->execute($finance, $data);
        FeeConfiguration::whereKey($fee->id)->update(['effective_from' => now()->subDay()]);
        try {
            $action->execute($finance, array_replace($data, ['amount' => '900.00']), $fee);
            $this->fail('Effective fee was overwritten.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('amount', $error->errors());
        }
        try {
            app(SaveOperatorAction::class)->execute($this->userWithRole('Auditor'), ['name' => 'Denied', 'status' => 'pending', 'park_ids' => [$pa->id], 'route_keys' => []]);
            $this->fail('Auditor action was allowed.');
        } catch (AuthorizationException $error) {
            $this->assertTrue(true);
        }
        $this->assertSame('500.00', $fee->fresh()->amount);
    }

    public function test_demo_seed_repeats_preserve_edits_and_ended_assignment_history(): void
    {
        config(['ospm.demo_mode' => true, 'ospm.demo_password' => 'SyntheticDemoPassword42']);
        $this->seed(DatabaseSeeder::class);
        $op = Operator::where('operator_number', 'DEMO-OPR-OSG')->firstOrFail();
        $op->update(['name' => 'Edited synthetic operator']);
        $assignment = DriverAssignment::firstOrFail();
        $actor = User::where('email', 'superadmin@demo.local')->firstOrFail();
        app(EndAssignmentAction::class)->execute($actor, $assignment, now()->toDateTimeString());
        $counts = [Operator::count(), Driver::count(), Vehicle::count(), DriverAssignment::count(), FeeConfiguration::count()];
        $this->seed(TransportRevenueDemoSeeder::class);
        $this->assertSame($counts, [Operator::count(), Driver::count(), Vehicle::count(), DriverAssignment::count(), FeeConfiguration::count()]);
        $this->assertSame('Edited synthetic operator', $op->fresh()->name);
        $this->assertSame('ended', $assignment->fresh()->status->value);
    }

    public function test_fee_scope_dates_and_currency_are_validated_on_the_server(): void
    {
        extract($this->network());
        $this->actingAs($this->userWithRole('Finance Administrator'));
        $head = RevenueHead::create(['code' => 'SCOPE', 'name' => 'Synthetic scope test', 'frequency' => 'daily', 'status' => 'active']);
        $this->post('/fee-configurations', $this->feeData($head, ['lga_id' => $b->id, 'park_id' => $pa->id]))->assertSessionHasErrors('lga_id');
        $this->post('/fee-configurations', $this->feeData($head, ['currency' => 'USD']))->assertSessionHasErrors('currency');
        $this->post('/fee-configurations', $this->feeData($head, ['effective_to' => now()->subDay()->toDateTimeString()]))->assertSessionHasErrors('effective_to');
        $head->update(['status' => 'inactive']);
        $this->post('/fee-configurations', $this->feeData($head))->assertSessionHasErrors('revenue_head_id');
        $this->assertSame(0, FeeConfiguration::count());
    }

    public function test_operator_can_be_suspended_after_its_existing_park_and_route_are_inactive(): void
    {
        extract($this->network());
        $pa->update(['status' => 'inactive']);
        $route->update(['status' => 'inactive']);
        $this->actingAs($state)->get('/operators/'.$oa->public_id.'/edit')->assertOk()->assertInertia(fn (Assert $p) => $p->has('options.operator_routes', 1));
        $this->patch('/operators/'.$oa->public_id, ['name' => $oa->name, 'status' => 'suspended', 'park_ids' => [$pa->id], 'route_keys' => [$pa->id.':'.$route->id]])->assertSessionHasNoErrors();
        $this->assertSame('suspended', $oa->fresh()->status->value);
    }
}
