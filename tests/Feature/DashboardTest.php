<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\FoundationTestCase;

class DashboardTest extends FoundationTestCase
{
    public function test_authorized_user_reaches_dashboard_with_backend_zero_states(): void
    {
        $this->actingAs($this->userWithRole('State Administrator'))->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Dashboard/State')->has('metrics', 9)->where('metrics.0.value', '0.00')->where('scope_label', 'Statewide')->has('finance.revenue_trend', 0));
    }

    public function test_scoped_user_cannot_access_state_dashboard_by_url(): void
    {
        $this->actingAs($this->userWithRole('LGA Administrator'))->get('/dashboard')->assertForbidden();
        $this->get('/account/access')->assertOk();
    }

    public function test_dashboard_date_range_validation_and_activity_filtering(): void
    {
        $user = $this->userWithRole('State Administrator');
        $this->actingAs($user)->get('/dashboard?from=2026-10-08&to=2026-10-07')->assertSessionHasErrors('to');
        $this->get('/dashboard?from=2020-01-01&to=2020-01-02')->assertInertia(fn (Assert $page) => $page->has('activities', 0)->where('filters.from', '2020-01-01'));
    }

    public function test_unknown_routes_use_the_friendly_error_page(): void
    {
        $this->get('/unknown-page')->assertNotFound()->assertInertia(fn (Assert $page) => $page->component('Errors/404')->where('system.name', 'Osun State Park Management System'));
    }

    public function test_unimplemented_modules_do_not_have_working_routes(): void
    {
        $this->actingAs($this->userWithRole('Super Administrator'));
        foreach (['/audit/activity', '/settings/general'] as $url) {
            $this->get($url)->assertNotFound();
        }
    }
}
