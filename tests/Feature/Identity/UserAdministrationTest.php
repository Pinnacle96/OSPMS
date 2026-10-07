<?php

namespace Tests\Feature\Identity;

use App\Domains\Identity\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\FoundationTestCase;

class UserAdministrationTest extends FoundationTestCase
{
    private function payload(): array
    {
        return ['name' => 'Synthetic Officer', 'email' => 'officer@demo.local', 'username' => 'synthetic.officer', 'status' => 'active', 'password' => 'TemporaryPassword7!', 'password_confirmation' => 'TemporaryPassword7!', 'must_change_password' => true, 'roles' => ['Park Manager']];
    }

    public function test_super_admin_can_create_view_edit_users_and_assign_roles(): void
    {
        $admin = $this->userWithRole('Super Administrator');
        $this->actingAs($admin)->post('/admin/users', $this->payload())->assertSessionHasNoErrors();
        $user = User::where('email', 'officer@demo.local')->firstOrFail();
        $this->assertTrue($user->hasRole('Park Manager'));
        $this->get('/admin/users/'.$user->public_id)->assertOk();
        $this->get('/admin/users/'.$user->public_id.'/edit')->assertOk();
        $data = $this->payload();
        $data['status'] = 'suspended';
        unset($data['password'], $data['password_confirmation']);
        $this->patch('/admin/users/'.$user->public_id, $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'suspended']);
        $this->assertDatabaseHas('activity_log', ['causer_id' => $admin->id, 'description' => 'user_roles_changed']);
    }

    public function test_unauthorized_roles_cannot_read_or_write_user_administration(): void
    {
        $target = User::factory()->create();
        foreach (['Auditor', 'Park Manager', 'LGA Administrator', 'Collection Agent', 'Transport Operator', 'State Administrator'] as $role) {
            $this->actingAs($this->userWithRole($role))->get('/admin/users')->assertForbidden();
            $this->get('/admin/users/'.$target->public_id)->assertForbidden();
            $this->post('/admin/users', $this->payload())->assertForbidden();
            $this->put('/admin/users/'.$target->public_id.'/scopes', ['lgas' => [], 'parks' => [], 'operators' => []])->assertForbidden();
        }
    }

    public function test_manage_users_permission_does_not_implicitly_grant_role_assignment(): void
    {
        $manager = User::factory()->create()->givePermissionTo('manage_users');
        $this->actingAs($manager)->post('/admin/users', $this->payload())->assertSessionHasErrors('roles');
        $this->assertDatabaseMissing('users', ['email' => 'officer@demo.local']);
        $data = $this->payload();
        unset($data['roles']);
        $this->post('/admin/users', $data)->assertSessionHasNoErrors();
    }

    public function test_role_permissions_can_be_assigned_and_changes_are_audited(): void
    {
        $admin = $this->userWithRole('Super Administrator');
        $role = Role::findByName('Park Manager');
        $this->actingAs($admin)->put('/admin/roles/'.$role->id, ['permissions' => ['view_park', 'verify_ticket']])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['view_park', 'verify_ticket'], $role->fresh()->permissions->pluck('name')->all());
        $this->assertDatabaseHas('activity_log', ['causer_id' => $admin->id, 'description' => 'role_permissions_changed']);
        $this->actingAs($this->userWithRole('Auditor'))->put('/admin/roles/'.$role->id, ['permissions' => []])->assertForbidden();
    }

    public function test_server_search_filter_sort_and_pagination(): void
    {
        $admin = $this->userWithRole('Super Administrator');
        User::factory()->count(17)->create(['status' => 'inactive']);
        User::factory()->create(['name' => 'Unique Synthetic Person']);
        $this->actingAs($admin)->get('/admin/users?search=Unique')->assertInertia(fn (Assert $page) => $page->component('Admin/Users/Index')->has('users.data', 1)->where('users.data.0.name', 'Unique Synthetic Person'));
        $this->get('/admin/users?status=inactive')->assertInertia(fn (Assert $page) => $page->where('users.total', 17)->has('users.data', 15));
        $this->get('/admin/users?status=inactive&page=2')->assertInertia(fn (Assert $page) => $page->has('users.data', 2));
        $this->get('/admin/users?sort=password')->assertSessionHasErrors('sort');
    }

    public function test_required_identity_unique_fields_and_role_names_are_validated(): void
    {
        $this->actingAs($this->userWithRole('Super Administrator'))->post('/admin/users', [])->assertSessionHasErrors(['name', 'email', 'username', 'password']);
        $data = $this->payload();
        $data['roles'] = ['Invented Role'];
        $this->post('/admin/users', $data)->assertSessionHasErrors('roles.0');
    }

    public function test_all_foundation_admin_screens_render_and_sensitive_fields_are_not_shared(): void
    {
        $admin = $this->userWithRole('Super Administrator');
        $this->actingAs($admin);
        foreach (['/admin/users', '/admin/users/create', '/admin/roles', '/admin/roles/'.Role::findByName('Auditor')->id, '/admin/permissions', '/admin/users/'.$admin->public_id.'/scopes', '/account/profile', '/account/security', '/account/access'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/account/access')->assertInertia(fn (Assert $page) => $page->missing('auth.user.password')->missing('auth.user.remember_token')->missing('system.demo_password'));
    }
}
