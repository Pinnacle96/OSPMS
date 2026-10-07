<?php

namespace Tests\Feature\Identity;

use App\Domains\Identity\Models\User;
use Spatie\Activitylog\Models\Activity;
use Tests\FoundationTestCase;

class DemoResetTest extends FoundationTestCase
{
    public function test_reset_refuses_when_demo_mode_is_false(): void
    {
        config(['ospm.demo_mode' => false]);
        $this->artisan('ospm:demo-reset')->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_reset_refuses_production_even_with_demo_mode_enabled(): void
    {
        config(['ospm.demo_mode' => true]);
        $this->app['env'] = 'production';
        $this->artisan('ospm:demo-reset')->assertFailed();
    }

    public function test_reset_restores_thirteen_demo_accounts_without_destroying_other_users_or_audits(): void
    {
        config(['ospm.demo_mode' => true, 'ospm.demo_password' => 'SyntheticDemoPassword7!']);
        $other = User::factory()->create();
        $this->artisan('ospm:demo-reset')->assertSuccessful();
        $this->assertDatabaseCount('users', 14);
        $count = Activity::count();
        $this->artisan('ospm:demo-reset')->assertSuccessful();
        $this->assertDatabaseCount('users', 14);
        $this->assertNotNull($other->fresh());
        $this->assertGreaterThanOrEqual($count, Activity::count());
        $this->assertTrue(User::where('email', 'superadmin@demo.local')->first()->can('manage_users'));
    }
}
