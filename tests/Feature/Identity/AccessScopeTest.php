<?php

namespace Tests\Feature\Identity;

use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Enums\AccessLevel;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Operators\Models\Operator;
use App\Domains\Parks\Models\Park;
use Tests\FoundationTestCase;

class AccessScopeTest extends FoundationTestCase
{
    private function lga(string $code): Lga
    {
        return Lga::create(['code' => $code, 'name' => 'Synthetic LGA '.$code, 'status' => 'active']);
    }

    private function park(Lga $lga, string $code): Park
    {
        return Park::create(['lga_id' => $lga->id, 'park_code' => $code, 'name' => 'Synthetic Park '.$code, 'address' => 'Synthetic address', 'status' => 'active']);
    }

    private function operator(string $code): Operator
    {
        return Operator::create(['operator_number' => $code, 'name' => 'Synthetic Operator '.$code, 'status' => 'approved']);
    }

    public function test_lga_park_operator_access_scopes_persist_and_are_audited(): void
    {
        $admin = $this->userWithRole('Super Administrator');
        $user = User::factory()->create();
        $lga = $this->lga('A');
        $park = $this->park($lga, 'P1');
        $operator = $this->operator('O1');
        $this->actingAs($admin)->put('/admin/users/'.$user->public_id.'/scopes', [
            'lgas' => [['id' => $lga->id, 'access_level' => 'view']], 'parks' => [['id' => $park->id, 'access_level' => 'manage']], 'operators' => [['id' => $operator->id, 'access_level' => 'view']],
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('user_lga_access', ['user_id' => $user->id, 'lga_id' => $lga->id, 'access_level' => 'view']);
        $this->assertDatabaseHas('user_park_access', ['user_id' => $user->id, 'park_id' => $park->id, 'access_level' => 'manage']);
        $this->assertDatabaseHas('user_operator_access', ['user_id' => $user->id, 'operator_id' => $operator->id, 'access_level' => 'view']);
        $this->assertDatabaseHas('activity_log', ['causer_id' => $admin->id, 'description' => 'user_scopes_changed']);
        $this->put('/admin/users/'.$user->public_id.'/scopes', ['lgas' => [], 'parks' => [], 'operators' => []])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('user_lga_access', 0);
    }

    public function test_lga_scope_cannot_cross_lga_boundaries_and_filters_queries(): void
    {
        $user = $this->userWithRole('LGA Administrator');
        $a = $this->lga('A');
        $b = $this->lga('B');
        $parkA = $this->park($a, 'P1');
        $parkB = $this->park($b, 'P2');
        $user->lgas()->attach($a, ['access_level' => 'manage', 'created_at' => now()]);
        $service = app(UserAccessScopeService::class);
        $this->assertTrue($service->canAccessLga($user, $a));
        $this->assertFalse($service->canAccessLga($user, $b));
        $this->assertTrue($service->canAccessPark($user, $parkA, AccessLevel::Manage));
        $this->assertFalse($service->canAccessPark($user, $parkB));
        $this->assertSame([$parkA->id], $service->accessibleParkIds($user));
        $this->assertSame([$a->id], $service->accessibleLgaIds($user));
    }

    public function test_park_view_scope_does_not_grant_manage_access_or_parent_lga_access(): void
    {
        $user = $this->userWithRole('Park Manager');
        $lga = $this->lga('A');
        $a = $this->park($lga, 'A');
        $b = $this->park($lga, 'B');
        $user->parks()->attach($a, ['access_level' => 'view', 'created_at' => now()]);
        $service = app(UserAccessScopeService::class);
        $this->assertTrue($service->canAccessPark($user, $a));
        $this->assertFalse($service->canAccessPark($user, $a, AccessLevel::Manage));
        $this->assertFalse($service->canAccessPark($user, $b));
        $this->assertFalse($service->canAccessLga($user, $lga));
    }

    public function test_operator_scope_cannot_access_another_operator_and_absent_scope_fails_closed(): void
    {
        $user = $this->userWithRole('Transport Operator');
        $a = $this->operator('A');
        $b = $this->operator('B');
        $service = app(UserAccessScopeService::class);
        $this->assertFalse($service->canAccessOperator($user, $a));
        $user->operators()->attach($a, ['access_level' => 'view', 'created_at' => now()]);
        $this->assertTrue($service->canAccessOperator($user, $a));
        $this->assertFalse($service->canAccessOperator($user, $b));
        $this->assertSame([$a->id], $service->accessibleOperatorIds($user));
    }

    public function test_statewide_access_is_a_permission_and_does_not_bypass_action_permissions(): void
    {
        $auditor = $this->userWithRole('Auditor');
        $lga = $this->lga('A');
        $this->assertTrue(app(UserAccessScopeService::class)->canAccessLga($auditor, $lga));
        $this->assertFalse($auditor->can('manage_park'));
        $this->assertFalse($auditor->can('manage_users'));
        $this->assertFalse($auditor->can('reverse_transaction'));
    }

    public function test_invalid_duplicate_and_deleted_scope_assignments_are_rejected_atomically(): void
    {
        $admin = $this->userWithRole('Super Administrator');
        $user = User::factory()->create();
        $lga = $this->lga('A');
        $payload = ['lgas' => [['id' => $lga->id, 'access_level' => 'invented']], 'parks' => [], 'operators' => []];
        $this->actingAs($admin)->put('/admin/users/'.$user->public_id.'/scopes', $payload)->assertSessionHasErrors('lgas.0.access_level');
        $payload['lgas'] = [['id' => $lga->id, 'access_level' => 'view'], ['id' => $lga->id, 'access_level' => 'manage']];
        $this->put('/admin/users/'.$user->public_id.'/scopes', $payload)->assertSessionHasErrors('lgas.0.id');
        $lga->delete();
        $payload['lgas'] = [['id' => $lga->id, 'access_level' => 'view']];
        $this->put('/admin/users/'.$user->public_id.'/scopes', $payload)->assertSessionHasErrors('lgas.0.id');
        $this->assertDatabaseCount('user_lga_access', 0);
    }
}
