<?php

namespace Tests\Feature\Identity;

use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\FoundationTestCase;

class AdminSessionTest extends FoundationTestCase
{
    public function test_admin_password_change_revokes_target_sessions_and_remember_token(): void
    {
        $admin = $this->userWithRole('Super Administrator');
        $user = User::factory()->create(['remember_token' => 'synthetic-old-token']);
        DB::table('sessions')->insert(['id' => 'target-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $this->actingAs($admin)->patch('/admin/users/'.$user->public_id, ['name' => $user->name, 'email' => $user->email, 'status' => 'active', 'password' => 'UpdatedPassword7!', 'password_confirmation' => 'UpdatedPassword7!', 'must_change_password' => true])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('UpdatedPassword7!', $user->fresh()->password));
        $this->assertNotEquals('synthetic-old-token', $user->fresh()->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'target-session']);
        $this->assertDatabaseHas('activity_log', ['causer_id' => $admin->id, 'description' => 'user_sessions_revoked']);
    }
}
