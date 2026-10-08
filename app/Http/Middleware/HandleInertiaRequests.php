<?php

namespace App\Http\Middleware;

use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\System\Services\PublicSystemConfig;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        if ($request->routeIs('public.ticket.verify', 'public.receipt.verify')) {
            return [
                ...parent::share($request),
                'auth' => ['user' => null, 'roles' => [], 'permissions' => [], 'scopes' => null],
                'system' => app(PublicSystemConfig::class)->get(),
                'navigation' => [], 'flash' => [], 'errors' => [], 'unread_notifications_count' => 0,
            ];
        }
        $user = $request->user();
        $nav = [];
        foreach ([
            ['Dashboard', '/dashboard', 'OVERVIEW', 'dashboard', 'view_state_dashboard'],
            ['LGAs', '/lgas', 'OPERATIONS', 'geography', 'view_lga'],
            ['Parks', '/parks', 'OPERATIONS', 'parks', 'view_park'],
            ['Routes', '/routes', 'OPERATIONS', 'routes', 'view_route'],
            ['Operators', '/operators', 'OPERATIONS', 'users', 'view_operator'],
            ['Drivers', '/drivers', 'OPERATIONS', 'user', 'view_driver'],
            ['Vehicles', '/vehicles', 'OPERATIONS', 'vehicle', 'view_vehicle'],
            ['Assignments', '/assignments', 'OPERATIONS', 'assignments', 'view_assignment'],
            ['Tickets', '/tickets', 'TICKETING', 'fees', 'view_ticket'],
            ['Payments', '/payments', 'FINANCE', 'revenue', 'view_payment'],
            ['Ledger', '/finance/ledger', 'FINANCE', 'revenue', 'view_financial_ledger'],
            ['Revenue heads', '/revenue-heads', 'FINANCE', 'revenue', 'view_revenue_head'],
            ['Fee configurations', '/fee-configurations', 'FINANCE', 'fees', 'view_fee_configuration'],
            ['Users', '/admin/users', 'ADMINISTRATION', 'users', 'manage_users'],
            ['Roles & permissions', '/admin/roles', 'ADMINISTRATION', 'shield', 'manage_roles'],
        ] as [$label, $href, $group, $icon, $permission]) {
            if ($user?->can($permission)) {
                $nav[] = compact('label', 'href', 'group', 'icon');
            }
        }
        $nav[] = ['label' => 'My account', 'href' => '/account/profile', 'group' => 'ACCOUNT', 'icon' => 'user'];
        $nav[] = ['label' => 'My access', 'href' => '/account/access', 'group' => 'ACCOUNT', 'icon' => 'key'];

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user?->only('public_id', 'name', 'email', 'username', 'status', 'must_change_password'),
                'roles' => $user?->getRoleNames()->values()->all() ?? [],
                'permissions' => $user?->getAllPermissions()->pluck('name')->values()->all() ?? [],
                'scopes' => $user ? app(UserAccessScopeService::class)->summary($user) : null,
            ],
            'system' => app(PublicSystemConfig::class)->get(),
            'flash' => ['success' => fn () => $request->session()->get('success'), 'error' => fn () => $request->session()->get('error'), 'status' => fn () => $request->session()->get('status')],
            'navigation' => $nav, 'unread_notifications_count' => 0,
        ];
    }
}
