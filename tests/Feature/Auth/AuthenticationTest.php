<?php

namespace Tests\Feature\Auth;

use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;
use Tests\FoundationTestCase;

class AuthenticationTest extends FoundationTestCase
{
    public function test_login_page_renders_and_guests_redirect_to_login(): void
    {
        $this->get('/login')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/Login'));
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/')->assertRedirect('/login');
    }

    public function test_active_user_can_log_in_using_email_and_logout_with_audit(): void
    {
        $user = $this->userWithRole('State Administrator');
        $this->post('/login', ['login' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('login_activities', ['user_id' => $user->id, 'event' => 'login_success']);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertDatabaseHas('login_activities', ['user_id' => $user->id, 'event' => 'logout']);
    }

    public function test_username_login_and_scoped_account_landing(): void
    {
        $user = $this->userWithRole('Park Manager');
        $user->update(['username' => 'demo.manager']);
        $this->post('/login', ['login' => 'DEMO.MANAGER', 'password' => 'password'])->assertRedirect('/account/access');
        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_and_suspended_users_cannot_log_in(): void
    {
        foreach ([UserStatus::Inactive, UserStatus::Suspended] as $status) {
            $user = User::factory()->create(['status' => $status]);
            $this->post('/login', ['login' => $user->email, 'password' => 'password'])->assertSessionHasErrors('login');
            $this->assertGuest();
            $this->assertDatabaseHas('login_activities', ['user_id' => $user->id, 'event' => 'login_failed']);
        }
    }

    public function test_deactivation_revokes_existing_session_access(): void
    {
        $user = $this->userWithRole('State Administrator');
        $this->actingAs($user);
        $user->update(['status' => UserStatus::Inactive]);
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertDatabaseHas('login_activities', ['event' => 'session_revoked']);
    }

    public function test_wrong_and_unknown_credentials_are_rejected_and_rate_limited(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('login');
        }
        $this->post('/login', ['login' => $user->email, 'password' => 'wrong'])->assertStatus(429);
        $this->post('/login', ['login' => 'unknown@demo.local', 'password' => 'wrong'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_password_recovery_and_token_reset(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
        $token = Password::createToken($user);
        $this->get('/reset-password/'.$token.'?email='.urlencode($user->email))->assertOk();
        $this->post('/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'ChangedPassword7!', 'password_confirmation' => 'ChangedPassword7!'])->assertRedirect('/login');
        $this->assertTrue(Hash::check('ChangedPassword7!', $user->fresh()->password));
        $this->assertDatabaseHas('login_activities', ['user_id' => $user->id, 'event' => 'password_reset']);
        $this->post('/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'AnotherPassword7!', 'password_confirmation' => 'AnotherPassword7!'])->assertSessionHasErrors('email');
    }

    public function test_required_password_change_blocks_other_pages_and_can_be_completed(): void
    {
        $user = $this->userWithRole('State Administrator');
        $user->update(['must_change_password' => true]);
        $this->actingAs($user)->get('/dashboard')->assertRedirect('/account/security');
        $this->get('/account/security')->assertOk();
        $this->put('/account/security/password', ['current_password' => 'password', 'password' => 'ChangedPassword7!', 'password_confirmation' => 'ChangedPassword7!'])->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->must_change_password);
        $this->get('/dashboard')->assertOk();
    }

    public function test_audit_records_exclude_passwords_and_reusable_session_tokens(): void
    {
        $user = User::factory()->create();
        $this->post('/login', ['login' => $user->email, 'password' => 'password']);
        $activity = $user->loginActivities()->first();
        $this->assertNotEquals(session()->getId(), $activity->session_identifier);
        $properties = Activity::pluck('properties')->toJson();
        $this->assertStringNotContainsString('"password":', $properties);
        $this->assertStringNotContainsString($user->password, $properties);
    }
}
