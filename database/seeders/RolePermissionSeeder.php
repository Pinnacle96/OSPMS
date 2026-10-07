<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public const REGISTRY_GRANTS = [
        'State Administrator' => ['manage_lga', 'create_park', 'view_route', 'manage_route', 'assign_park_routes'],
        'Executive Viewer' => ['view_route'],
        'Finance Administrator' => ['view_route'],
        'Revenue Officer' => ['view_route'],
        'Auditor' => ['view_route'],
        'LGA Administrator' => ['view_route', 'assign_park_routes'],
        'Park Manager' => ['view_route'],
    ];

    public const GRANTS = [
        'Super Administrator' => ['*'],
        'State Administrator' => ['access_statewide', 'view_state_dashboard', 'view_state_revenue', 'view_lga_revenue', 'view_park_revenue', 'view_lga', 'view_park', 'view_operator', 'manage_park', 'create_driver', 'edit_driver', 'approve_driver', 'suspend_driver', 'create_vehicle', 'approve_vehicle', 'create_operator', 'approve_operator', 'view_payment', 'view_audit_log'],
        'Executive Viewer' => ['access_statewide', 'view_state_dashboard', 'view_state_revenue', 'view_lga', 'view_park', 'view_operator', 'view_payment'],
        'Finance Administrator' => ['access_statewide', 'view_state_dashboard', 'view_state_revenue', 'view_lga_revenue', 'view_park_revenue', 'view_payment', 'reverse_transaction', 'approve_refund', 'reconcile_transaction', 'view_audit_log', 'view_lga', 'view_park', 'view_operator'],
        'Revenue Officer' => ['access_statewide', 'view_state_dashboard', 'view_state_revenue', 'view_lga_revenue', 'view_park_revenue', 'view_payment', 'view_lga', 'view_park', 'view_operator'],
        'Auditor' => ['access_statewide', 'view_state_dashboard', 'view_state_revenue', 'view_lga_revenue', 'view_park_revenue', 'view_payment', 'view_audit_log', 'view_lga', 'view_park', 'view_operator'],
        'LGA Administrator' => ['view_lga', 'view_park', 'view_operator', 'manage_park', 'view_lga_revenue', 'view_park_revenue', 'create_driver', 'edit_driver', 'approve_driver', 'create_vehicle', 'approve_vehicle', 'create_operator', 'approve_operator', 'view_payment'],
        'Park Manager' => ['view_park', 'view_operator', 'manage_park', 'view_park_revenue', 'create_driver', 'edit_driver', 'create_vehicle', 'view_payment'],
        'Ticketing Officer' => ['view_park', 'view_operator', 'issue_ticket', 'verify_ticket', 'view_payment'],
        'Collection Agent' => ['view_park', 'view_operator', 'issue_ticket', 'verify_ticket', 'view_payment'],
        'Enforcement Officer' => ['view_park', 'view_operator', 'verify_ticket'],
        'Help Desk Officer' => ['view_park', 'view_operator'],
        'Transport Operator' => ['view_park', 'view_operator', 'view_payment'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = collect(self::GRANTS)->flatten()->merge(collect(self::REGISTRY_GRANTS)->flatten())->reject(fn ($name) => $name === '*')->push('manage_users', 'manage_roles')->unique();
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        foreach (self::GRANTS as $name => $grants) {
            if ($grants !== ['*']) {
                $grants = array_merge($grants, self::REGISTRY_GRANTS[$name] ?? []);
            }
            Role::findOrCreate($name, 'web')->syncPermissions($grants === ['*'] ? Permission::where('guard_name', 'web')->get() : $grants);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
