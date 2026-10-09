<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Identity\Models\User;

class DashboardDestination
{
    public function route(User $user): string
    {
        if ($user->can('access_field') && ! $user->can('view_state_dashboard') && ! $user->can('view_revenue_dashboard')) {
            return 'field.home';
        }
        if ($user->can('view_executive_dashboard') && ! $user->can('view_revenue_dashboard')) {
            return 'dashboard.executive';
        }
        if ($user->can('view_state_dashboard')) {
            return 'dashboard.state';
        }

        return $user->can('view_revenue_dashboard') ? 'dashboard.revenue' : 'account.access';
    }
}
