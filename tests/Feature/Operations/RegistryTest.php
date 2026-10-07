<?php

namespace Tests\Feature\Operations;

use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Models\User;
use App\Domains\Parks\Actions\SaveParkAction;
use App\Domains\Parks\Models\Park;
use App\Domains\Routes\Models\Route;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\RegistryDemoSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\FoundationTestCase;

class RegistryTest extends FoundationTestCase
{
    private function network(): array
    {
        $a = Lga::create(['code' => 'A', 'name' => 'Synthetic LGA A', 'status' => 'active']);
        $b = Lga::create(['code' => 'B', 'name' => 'Synthetic LGA B', 'status' => 'active']);
        $pa = Park::create(['lga_id' => $a->id, 'park_code' => 'PA', 'name' => 'Synthetic Park A', 'address' => 'Synthetic address A', 'status' => 'active']);
        $pb = Park::create(['lga_id' => $b->id, 'park_code' => 'PB', 'name' => 'Synthetic Park B', 'address' => 'Synthetic address B', 'status' => 'active']);
        $shared = Route::create(['route_code' => 'SHARED', 'origin' => 'Origin A', 'destination' => 'Destination A', 'status' => 'active']);
        $onlyB = Route::create(['route_code' => 'ONLY-B', 'origin' => 'Origin B', 'destination' => 'Destination B', 'status' => 'active']);
        $free = Route::create(['route_code' => 'FREE', 'origin' => 'Free origin', 'destination' => 'Free destination', 'status' => 'active']);
        $pa->routes()->attach($shared, ['status' => 'active', 'created_at' => now()]);
        $pb->routes()->attach($shared, ['status' => 'active', 'created_at' => now()]);
        $pb->routes()->attach($onlyB, ['status' => 'active', 'created_at' => now()]);

        return [$a, $b, $pa, $pb, $shared, $onlyB, $free];
    }

    private function parkData(Park $park, array $overrides = []): array
    {
        return array_replace($park->only('park_code', 'lga_id', 'name', 'address'), ['status' => $park->status->value], $overrides);
    }

    public function test_state_admin_creates_registries_and_all_fourteen_screens_render(): void
    {
        $admin = $this->userWithRole('State Administrator');
        $this->actingAs($admin)->post('/lgas', ['code' => 'NEW', 'name' => 'New Synthetic LGA', 'status' => 'active'])->assertSessionHasNoErrors();
        $lga = Lga::firstOrFail();
        $this->post('/parks', ['park_code' => 'NEW-P', 'name' => 'New Synthetic Park', 'lga_id' => $lga->id, 'address' => 'Synthetic address', 'status' => 'pending', 'created_by' => 999, 'activated_at' => '2001-01-01'])->assertSessionHasNoErrors();
        $park = Park::firstOrFail();
        $this->assertSame($admin->id, $park->created_by);
        $this->assertNull($park->activated_at);
        $this->post('/routes', ['route_code' => 'NEW-R', 'origin' => 'Origin', 'destination' => 'Destination', 'status' => 'active'])->assertSessionHasNoErrors();
        $route = Route::firstOrFail();
        foreach (['lgas' => $lga, 'parks' => $park, 'routes' => $route] as $kind => $record) {
            $page = ucfirst($kind);
            $this->get('/'.$kind)->assertOk()->assertInertia(fn (Assert $p) => $p->component($page.'/Index')->has('records.data', 1));
            $this->get('/'.$kind.'/create')->assertOk()->assertInertia(fn (Assert $p) => $p->component($page.'/Create'));
            $this->get('/'.$kind.'/'.$record->public_id)->assertOk()->assertInertia(fn (Assert $p) => $p->component($page.'/Show'));
            $this->get('/'.$kind.'/'.$record->public_id.'/edit')->assertOk()->assertInertia(fn (Assert $p) => $p->component($page.'/Edit'));
        }
        $this->get('/lgas/'.$lga->public_id.'/dashboard')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Dashboard/Lga'));
        $this->get('/parks/'.$park->public_id.'/dashboard')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Dashboard/Park'));
        foreach (['lga_created', 'park_created', 'route_created'] as $event) {
            $this->assertDatabaseHas('activity_log', ['description' => $event, 'causer_id' => $admin->id]);
        }
        $this->get('/parks/'.$park->id)->assertNotFound();
    }

    public function test_park_activation_suspension_and_reactivation_are_audited_without_resetting_first_activation(): void
    {
        [$a,$b,$park] = $this->network();
        $park->update(['status' => 'pending']);
        $admin = $this->userWithRole('State Administrator');
        $this->actingAs($admin)->patch('/parks/'.$park->public_id, $this->parkData($park, ['status' => 'active']))->assertSessionHasNoErrors();
        $activated = $park->fresh()->activated_at->toDateTimeString();
        foreach (['suspended', 'inactive', 'active'] as $status) {
            $this->patch('/parks/'.$park->public_id, $this->parkData($park, ['status' => $status]))->assertSessionHasNoErrors();
        }
        $this->assertSame($activated, $park->fresh()->activated_at->toDateTimeString());
        $this->assertSame($admin->id, $park->fresh()->updated_by);
        $this->assertDatabaseHas('activity_log', ['description' => 'park_status_changed', 'subject_id' => $park->id]);
        $this->assertSame(4, DB::table('activity_log')->where('description', 'park_status_changed')->count());
    }

    public function test_inactive_lga_cannot_activate_a_park_and_active_children_block_lga_deactivation(): void
    {
        [$a,$b,$pa,$pb] = $this->network();
        $this->actingAs($this->userWithRole('State Administrator'))->patch('/lgas/'.$a->public_id, ['code' => $a->code, 'name' => $a->name, 'status' => 'inactive'])->assertSessionHasErrors('status');
        $this->assertSame('active', $a->fresh()->status->value);
        $pa->update(['status' => 'inactive']);
        $a->update(['status' => 'inactive']);
        $this->patch('/parks/'.$pa->public_id, $this->parkData($pa, ['status' => 'active']))->assertSessionHasErrors('status');
        $this->assertSame('inactive', $pa->fresh()->status->value);
        $this->assertDatabaseMissing('activity_log', ['description' => 'park_status_changed']);
    }

    public function test_lga_admin_lists_and_direct_urls_cannot_cross_lga_boundaries(): void
    {
        [$a,$b,$pa,$pb] = $this->network();
        $user = $this->userWithRole('LGA Administrator');
        $user->lgas()->attach($a, ['access_level' => 'manage', 'created_at' => now()]);
        $this->actingAs($user)->get('/lgas')->assertInertia(fn (Assert $p) => $p->has('records.data', 1)->where('records.data.0.public_id', $a->public_id));
        $this->get('/parks')->assertInertia(fn (Assert $p) => $p->has('records.data', 1)->where('records.data.0.public_id', $pa->public_id)->has('lgas', 1));
        foreach (['/lgas/'.$b->public_id, '/lgas/'.$b->public_id.'/dashboard', '/parks/'.$pb->public_id, '/parks/'.$pb->public_id.'/edit', '/parks/'.$pb->public_id.'/dashboard'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->patch('/parks/'.$pb->public_id, $this->parkData($pb, ['name' => 'Tampered']))->assertForbidden();
        $this->get('/parks?search=Park%20B')->assertInertia(fn (Assert $p) => $p->has('records.data', 0)->where('records.total', 0));
        $this->get('/parks?lga_id='.$b->id)->assertInertia(fn (Assert $p) => $p->has('records.data', 0));
        $this->get('/lgas?search=Synthetic%20LGA%20B')->assertInertia(fn (Assert $p) => $p->has('records.data', 0));
    }

    public function test_shared_route_does_not_reveal_other_lga_parks_or_counts(): void
    {
        [$a,$b,$pa,$pb,$shared,$onlyB,$free] = $this->network();
        $user = $this->userWithRole('LGA Administrator');
        $user->lgas()->attach($a, ['access_level' => 'manage', 'created_at' => now()]);
        $this->actingAs($user)->get('/routes')->assertInertia(fn (Assert $p) => $p->has('records.data', 1)->where('records.data.0.public_id', $shared->public_id)->where('records.data.0.parks_count', 1));
        $this->get('/routes/'.$shared->public_id)->assertInertia(fn (Assert $p) => $p->has('parks.data', 1)->where('parks.data.0.public_id', $pa->public_id));
        $this->get('/routes/'.$onlyB->public_id)->assertForbidden();
        $this->get('/routes/'.$free->public_id)->assertForbidden();
        $this->get('/routes?lga_id='.$b->id)->assertInertia(fn (Assert $p) => $p->has('records.data', 0));
        $this->get('/routes?search=ONLY-B')->assertInertia(fn (Assert $p) => $p->has('records.data', 0));
    }

    public function test_park_manager_access_is_limited_to_assigned_park_and_cannot_create_or_move_it(): void
    {
        [$a,$b,$pa,$pb] = $this->network();
        $user = $this->userWithRole('Park Manager');
        $user->parks()->attach($pa, ['access_level' => 'manage', 'created_at' => now()]);
        $this->actingAs($user)->get('/parks')->assertInertia(fn (Assert $p) => $p->has('records.data', 1));
        $this->get('/parks/'.$pa->public_id)->assertInertia(fn (Assert $p) => $p->where('can_view_lga', false)->where('can_assign_routes', false));
        $this->get('/parks/'.$pa->public_id.'/dashboard')->assertOk();
        $this->get('/parks/'.$pb->public_id)->assertForbidden();
        $this->get('/lgas/'.$a->public_id)->assertForbidden();
        $this->get('/parks/create')->assertForbidden();
        $this->patch('/parks/'.$pa->public_id, $this->parkData($pa, ['lga_id' => $b->id]))->assertForbidden();
        $this->assertSame($a->id, $pa->fresh()->lga_id);
        $this->patch('/parks/'.$pa->public_id, $this->parkData($pa, ['contact_phone' => '08000000000']))->assertSessionHasNoErrors();
    }

    public function test_view_scope_does_not_grant_park_writes_or_route_assignment(): void
    {
        [$a,$b,$pa,$pb,$shared] = $this->network();
        $user = $this->userWithRole('LGA Administrator');
        $user->lgas()->attach($a, ['access_level' => 'view', 'created_at' => now()]);
        $this->actingAs($user)->get('/parks/'.$pa->public_id)->assertOk();
        $this->get('/parks/'.$pa->public_id.'/edit')->assertForbidden();
        $this->patch('/parks/'.$pa->public_id, $this->parkData($pa, ['status' => 'suspended']))->assertForbidden();
        $this->put('/parks/'.$pa->public_id.'/routes', ['route_ids' => [$shared->id]])->assertForbidden();
        $this->assertSame('active', $pa->fresh()->status->value);
    }

    public function test_lga_manager_can_edit_own_park_but_cannot_move_it_outside_managed_scopes(): void
    {
        [$a,$b,$pa,$pb] = $this->network();
        $user = $this->userWithRole('LGA Administrator');
        $user->lgas()->attach($a, ['access_level' => 'manage', 'created_at' => now()]);
        $this->actingAs($user)->get('/parks/'.$pa->public_id.'/edit')->assertInertia(fn (Assert $p) => $p->has('lgas', 1));
        $this->patch('/parks/'.$pa->public_id, $this->parkData($pa, ['lga_id' => $b->id]))->assertForbidden();
        $this->assertSame($a->id, $pa->fresh()->lga_id);
        $this->patch('/parks/'.$pa->public_id, $this->parkData($pa, ['status' => 'suspended']))->assertSessionHasNoErrors();
        $this->assertSame('suspended', $pa->fresh()->status->value);
        $this->patch('/lgas/'.$a->public_id, ['code' => 'A', 'name' => 'Changed', 'status' => 'active'])->assertForbidden();
        $this->get('/routes/create')->assertForbidden();
    }

    public function test_read_only_statewide_roles_cannot_write_or_archive_registries(): void
    {
        [$a,$b,$pa,$pb,$shared] = $this->network();
        foreach (['Auditor', 'Executive Viewer', 'Finance Administrator', 'Revenue Officer'] as $role) {
            $this->actingAs($this->userWithRole($role));
            foreach (['lgas' => $a, 'parks' => $pa, 'routes' => $shared] as $kind => $record) {
                $this->get('/'.$kind)->assertOk();
                $this->get('/'.$kind.'/create')->assertForbidden();
                $this->patch('/'.$kind.'/'.$record->public_id, [])->assertForbidden();
                $this->delete('/'.$kind.'/'.$record->public_id)->assertForbidden();
            }
            $this->put('/parks/'.$pa->public_id.'/routes', ['route_ids' => []])->assertForbidden();
        }
    }

    public function test_geographic_permission_does_not_bypass_action_permissions_and_empty_scopes_fail_closed(): void
    {
        $this->network();
        $user = User::factory()->create()->givePermissionTo('access_statewide');
        $this->actingAs($user)->get('/parks')->assertForbidden();
        $this->get('/routes')->assertForbidden();
        $user = $this->userWithRole('LGA Administrator');
        $this->actingAs($user)->get('/lgas')->assertInertia(fn (Assert $p) => $p->has('records.data', 0));
        $this->get('/parks')->assertInertia(fn (Assert $p) => $p->has('records.data', 0));
        $this->get('/routes')->assertInertia(fn (Assert $p) => $p->has('records.data', 0));
    }

    public function test_route_assignment_replacement_keeps_history_and_other_parks_unchanged(): void
    {
        [$a,$b,$pa,$pb,$shared,$onlyB,$free] = $this->network();
        $user = $this->userWithRole('LGA Administrator');
        $user->lgas()->attach($a, ['access_level' => 'manage', 'created_at' => now()]);
        $original = DB::table('park_route')->where('park_id', $pa->id)->where('route_id', $shared->id)->first();
        $this->actingAs($user)->put('/parks/'.$pa->public_id.'/routes', ['route_ids' => [$free->id]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('park_route', ['id' => $original->id, 'status' => 'inactive', 'created_at' => $original->created_at]);
        $this->assertDatabaseHas('park_route', ['park_id' => $pb->id, 'route_id' => $shared->id, 'status' => 'active']);
        $this->assertDatabaseHas('park_route', ['park_id' => $pa->id, 'route_id' => $free->id, 'status' => 'active']);
        $this->assertDatabaseHas('activity_log', ['description' => 'park_routes_changed', 'causer_id' => $user->id]);
        $this->put('/parks/'.$pa->public_id.'/routes', ['route_ids' => [$shared->id, $free->id]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('park_route', ['id' => $original->id, 'status' => 'active', 'created_at' => $original->created_at]);
        $this->put('/parks/'.$pa->public_id.'/routes', ['route_ids' => []])->assertSessionHasNoErrors();
        $this->assertSame(0, DB::table('park_route')->where('park_id', $pa->id)->where('status', 'active')->count());
        $this->get('/routes/'.$shared->public_id)->assertForbidden();
    }

    public function test_invalid_duplicate_inactive_deleted_and_foreign_scope_route_assignments_are_atomic(): void
    {
        [$a,$b,$pa,$pb,$shared,$onlyB,$free] = $this->network();
        $user = $this->userWithRole('LGA Administrator');
        $user->lgas()->attach($a, ['access_level' => 'manage', 'created_at' => now()]);
        $free->update(['status' => 'inactive']);
        $this->actingAs($user)->put('/parks/'.$pa->public_id.'/routes', ['route_ids' => [$shared->id, $shared->id]])->assertSessionHasErrors('route_ids.0');
        $this->put('/parks/'.$pa->public_id.'/routes', ['route_ids' => [$shared->id, $free->id]])->assertSessionHasErrors('route_ids.1');
        $free->delete();
        $this->put('/parks/'.$pa->public_id.'/routes', ['route_ids' => [$free->id]])->assertSessionHasErrors('route_ids.0');
        $this->put('/parks/'.$pa->public_id.'/routes', ['route_ids' => [999999]])->assertSessionHasErrors('route_ids.0');
        $this->put('/parks/'.$pb->public_id.'/routes', ['route_ids' => []])->assertForbidden();
        $this->assertDatabaseHas('park_route', ['park_id' => $pa->id, 'route_id' => $shared->id, 'status' => 'active']);
        $this->assertDatabaseMissing('activity_log', ['description' => 'park_routes_changed']);
    }

    public function test_archiving_is_soft_audited_and_blocked_for_any_linked_history(): void
    {
        [$a,$b,$pa,$pb,$shared,$onlyB,$free] = $this->network();
        $this->actingAs($this->userWithRole('State Administrator'));
        foreach (['lgas' => $a, 'parks' => $pa, 'routes' => $shared] as $kind => $record) {
            $this->delete('/'.$kind.'/'.$record->public_id)->assertSessionHasErrors('archive');
        }
        DB::table('park_route')->where('park_id', $pa->id)->update(['status' => 'inactive']);
        $this->delete('/parks/'.$pa->public_id)->assertSessionHasErrors('archive');
        $this->delete('/routes/'.$free->public_id)->assertSessionHasNoErrors();
        $this->assertSoftDeleted('routes', ['id' => $free->id]);
        $this->assertDatabaseHas('activity_log', ['description' => 'route_archived', 'subject_id' => $free->id]);
        $this->get('/routes/'.$free->public_id)->assertNotFound();
        $lga = Lga::create(['code' => 'EMPTY', 'name' => 'Empty Synthetic LGA', 'status' => 'active']);
        $park = Park::create(['park_code' => 'EMPTY-P', 'lga_id' => $lga->id, 'name' => 'Empty Park', 'address' => 'Synthetic', 'status' => 'pending']);
        $this->delete('/parks/'.$park->public_id)->assertSessionHasNoErrors();
        $this->assertSoftDeleted('parks', ['id' => $park->id]);
        $this->delete('/lgas/'.$lga->public_id)->assertSessionHasErrors('archive');
        $empty = Lga::create(['code' => 'NO-HISTORY', 'name' => 'No History LGA', 'status' => 'active']);
        $this->delete('/lgas/'.$empty->public_id)->assertSessionHasNoErrors();
        $this->assertSoftDeleted('lgas', ['id' => $empty->id]);
    }

    public function test_status_changes_for_lga_and_route_are_audited(): void
    {
        [$a,$b,$pa,$pb,$shared,$onlyB,$free] = $this->network();
        $this->actingAs($this->userWithRole('State Administrator'))->patch('/routes/'.$free->public_id, ['route_code' => $free->route_code, 'origin' => $free->origin, 'destination' => $free->destination, 'status' => 'inactive'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('activity_log', ['description' => 'route_status_changed', 'subject_id' => $free->id]);
        $lga = Lga::create(['code' => 'EMPTY', 'name' => 'Empty Synthetic', 'status' => 'active']);
        $this->patch('/lgas/'.$lga->public_id, ['code' => $lga->code, 'name' => $lga->name, 'status' => 'inactive'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('activity_log', ['description' => 'lga_status_changed', 'subject_id' => $lga->id]);
    }

    public function test_validation_enforces_codes_status_coordinates_and_required_relationships(): void
    {
        [$a,$b,$pa,$pb,$shared] = $this->network();
        $this->actingAs($this->userWithRole('State Administrator'))->post('/lgas', ['code' => $a->code, 'name' => $a->name, 'status' => 'suspended'])->assertSessionHasErrors(['code', 'name', 'status']);
        $this->post('/parks', $this->parkData($pa, ['latitude' => 91, 'longitude' => -181]))->assertSessionHasErrors(['park_code', 'latitude', 'longitude']);
        $b->delete();
        $this->post('/parks', $this->parkData($pa, ['park_code' => 'OTHER', 'lga_id' => $b->id, 'address' => '', 'status' => 'invented']))->assertSessionHasErrors(['lga_id', 'address', 'status']);
        $this->post('/routes', ['route_code' => $shared->route_code, 'origin' => 'Same', 'destination' => 'Same', 'status' => 'suspended'])->assertSessionHasErrors(['route_code', 'destination', 'status']);
        foreach (['lgas', 'parks', 'routes'] as $kind) {
            $this->get('/'.$kind.'?sort=password')->assertSessionHasErrors('sort');
        }
    }

    public function test_server_filters_sort_and_pagination_use_database_queries(): void
    {
        [$a,$b,$pa,$pb,$shared] = $this->network();
        for ($i = 0; $i < 16; $i++) {
            Lga::create(['code' => 'EX-'.$i, 'name' => sprintf('Extra Synthetic %02d', $i), 'status' => 'inactive']);
        }
        $this->actingAs($this->userWithRole('State Administrator'))->get('/lgas?status=inactive&sort=name&direction=desc')->assertInertia(fn (Assert $p) => $p->where('records.total', 16)->has('records.data', 15)->where('records.data.0.name', 'Extra Synthetic 15'));
        $this->get('/lgas?status=inactive&page=2')->assertInertia(fn (Assert $p) => $p->has('records.data', 1));
        $this->get('/parks?search=PB&lga_id='.$b->id)->assertInertia(fn (Assert $p) => $p->has('records.data', 1)->where('records.data.0.public_id', $pb->public_id));
        $this->get('/routes?search=Origin%20A')->assertInertia(fn (Assert $p) => $p->has('records.data', 1)->where('records.data.0.public_id', $shared->public_id));
    }

    public function test_scoped_dashboards_have_real_registry_counts_and_explicit_financial_zero_states(): void
    {
        [$a,$b,$pa,$pb] = $this->network();
        $user = $this->userWithRole('LGA Administrator');
        $user->lgas()->attach($a, ['access_level' => 'manage', 'created_at' => now()]);
        $this->actingAs($user)->get('/lgas/'.$a->public_id.'/dashboard')->assertInertia(fn (Assert $p) => $p->where('record.public_id', $a->public_id)->where('metrics.0.value', 1)->where('metrics.1.value', 1)->where('metrics.2.value', 1)->where('metrics.9.value', '0.00')->where('metrics.10.value', '0.00'));
        $this->get('/parks/'.$pa->public_id.'/dashboard')->assertInertia(fn (Assert $p) => $p->where('metrics.0.value', 1)->where('metrics.7.value', '0.00'));
        $this->get('/parks/'.$pb->public_id.'/dashboard')->assertForbidden();
        $this->actingAs($this->userWithRole('State Administrator'))->get('/dashboard')->assertInertia(fn (Assert $p) => $p->where('metrics.2.value', 2)->where('lga_count', 2));
    }

    public function test_direct_domain_action_calls_still_enforce_authorization(): void
    {
        [$a,$b,$pa] = $this->network();
        $this->expectException(AuthorizationException::class);
        app(SaveParkAction::class)->execute($this->userWithRole('Auditor'), $this->parkData($pa, ['name' => 'Unauthorized']), $pa);
    }

    public function test_demo_registry_seeding_is_idempotent_and_preserves_user_edits(): void
    {
        config(['ospm.demo_mode' => true, 'ospm.demo_password' => 'SyntheticDemoPassword7!']);
        $this->seed(DemoUserSeeder::class);
        $this->seed(RegistryDemoSeeder::class);
        Park::where('park_code', 'DEMO-P-OSG-01')->firstOrFail()->update(['name' => 'Edited demo park']);
        $archived = Route::where('route_code', 'DEMO-R-003')->firstOrFail();
        $archived->delete();
        $this->seed(RegistryDemoSeeder::class);
        $this->assertDatabaseCount('lgas', 3);
        $this->assertDatabaseCount('parks', 3);
        $this->assertDatabaseCount('routes', 3);
        $this->assertDatabaseCount('park_route', 3);
        $this->assertDatabaseHas('parks', ['name' => 'Edited demo park']);
        $this->assertSoftDeleted('routes', ['id' => $archived->id]);
        $user = User::where('email', 'lgaadmin@demo.local')->firstOrFail();
        $this->assertSame(1, $user->lgas()->count());
        $this->actingAs($user)->get('/parks')->assertInertia(fn (Assert $p) => $p->where('records.total', 1));
    }
}
