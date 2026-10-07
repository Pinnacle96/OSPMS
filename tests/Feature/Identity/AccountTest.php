<?php

namespace Tests\Feature\Identity;

use App\Domains\Identity\Enums\UserStatus;
use Illuminate\Support\Facades\DB;
use Tests\FoundationTestCase;

class AccountTest extends FoundationTestCase
{
    public function test_profile_accepts_username_update_without_overwriting_omitted_email(): void
    {
        $user = $this->userWithRole('Park Manager');
        $this->actingAs($user)->patch('/account/profile', ['name' => $user->name, 'username' => 'synthetic.username'])->assertSessionHasNoErrors();
        $this->assertSame('synthetic.username', $user->fresh()->username);
        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_profile_update_cannot_elevate_account_status_or_permissions(): void
    {
        $user = $this->userWithRole('Park Manager');
        $this->actingAs($user)->patch('/account/profile', ['name' => 'Updated Synthetic Name', 'email' => $user->email, 'username' => 'updated.user', 'phone' => '00000000000', 'status' => 'suspended', 'roles' => ['Super Administrator']])->assertSessionHasNoErrors();
        $this->assertSame('Updated Synthetic Name', $user->fresh()->name);
        $this->assertSame(UserStatus::Active, $user->fresh()->status);
        $this->assertFalse($user->fresh()->can('manage_users'));
    }

    public function test_session_revocation_requires_password_and_only_deletes_own_other_sessions(): void
    {
        $user = $this->userWithRole('Park Manager');
        $other = $this->userWithRole('Auditor');
        DB::table('sessions')->insert([
            ['id' => 'own-other', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'another-person', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()],
        ]);
        $this->actingAs($user)->delete('/account/security/sessions', ['current_password' => 'wrong'])->assertSessionHasErrors('current_password');
        $this->assertDatabaseHas('sessions', ['id' => 'own-other']);
        $this->delete('/account/security/sessions', ['current_password' => 'password'])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('sessions', ['id' => 'own-other']);
        $this->assertDatabaseHas('sessions', ['id' => 'another-person']);
    }
}
