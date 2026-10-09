<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public const FIELD_GRANTS = ['Enforcement Officer' => ['access_field', 'field_lookup', 'record_inspection']];

    public const CORRECTION_GRANTS = [
        'Finance Administrator' => ['view_refund', 'request_refund', 'process_refund', 'view_adjustment', 'request_adjustment', 'approve_adjustment'],
        'Auditor' => ['view_refund', 'view_adjustment'],
        'State Administrator' => ['view_refund', 'request_refund'],
        'LGA Administrator' => ['view_refund', 'request_refund'],
        'Park Manager' => ['view_refund', 'request_refund'],
    ];

    public const RECONCILIATION_GRANTS = [
        'Finance Administrator' => ['view_settlement', 'create_settlement', 'view_reconciliation', 'resolve_reconciliation_exception'],
        'Auditor' => ['view_settlement', 'view_reconciliation'],
        'State Administrator' => ['view_reconciliation'],
        'Executive Viewer' => ['view_reconciliation'],
        'Revenue Officer' => ['view_reconciliation'],
        'LGA Administrator' => ['view_reconciliation'],
    ];

    public const DASHBOARD_GRANTS = [
        'State Administrator' => ['view_executive_dashboard', 'view_revenue_dashboard'],
        'Executive Viewer' => ['view_executive_dashboard', 'view_lga_revenue', 'view_park_revenue'],
        'Finance Administrator' => ['view_executive_dashboard', 'view_revenue_dashboard'],
        'Revenue Officer' => ['view_revenue_dashboard'],
        'Auditor' => ['view_executive_dashboard', 'view_revenue_dashboard'],
    ];

    public const PAYMENT_GRANTS = [
        'State Administrator' => ['view_receipt', 'view_financial_ledger'],
        'Executive Viewer' => ['view_receipt', 'view_financial_ledger'],
        'Finance Administrator' => ['view_receipt', 'view_financial_ledger'],
        'Revenue Officer' => ['view_receipt', 'view_financial_ledger'],
        'Auditor' => ['view_receipt', 'view_financial_ledger'],
        'LGA Administrator' => ['view_receipt', 'view_financial_ledger'],
        'Park Manager' => ['view_receipt'],
        'Ticketing Officer' => ['view_receipt', 'collect_payment'],
        'Collection Agent' => ['view_receipt', 'collect_payment'],
        'Transport Operator' => ['view_receipt'],
    ];

    public const TICKETING_GRANTS = [
        'State Administrator' => ['view_ticket', 'cancel_ticket'],
        'Executive Viewer' => ['view_ticket'],
        'Finance Administrator' => ['view_ticket', 'cancel_ticket'],
        'Revenue Officer' => ['view_ticket'],
        'Auditor' => ['view_ticket'],
        'LGA Administrator' => ['view_ticket', 'cancel_ticket'],
        'Park Manager' => ['view_ticket', 'cancel_ticket'],
        'Ticketing Officer' => ['view_ticket'],
        'Collection Agent' => ['view_ticket'],
        'Enforcement Officer' => ['view_ticket'],
        'Transport Operator' => ['view_ticket'],
    ];

    public const REGISTRY_GRANTS = [
        'State Administrator' => ['manage_lga', 'create_park', 'view_route', 'manage_route', 'assign_park_routes'],
        'Executive Viewer' => ['view_route'],
        'Finance Administrator' => ['view_route'],
        'Revenue Officer' => ['view_route'],
        'Auditor' => ['view_route'],
        'LGA Administrator' => ['view_route', 'assign_park_routes'],
        'Park Manager' => ['view_route'],
    ];

    public const TRANSPORT_GRANTS = [
        'State Administrator' => ['edit_operator', 'suspend_operator', 'view_driver', 'view_vehicle', 'edit_vehicle', 'suspend_vehicle', 'view_assignment', 'manage_assignment', 'view_revenue_head', 'view_fee_configuration'],
        'LGA Administrator' => ['edit_operator', 'suspend_operator', 'view_driver', 'view_vehicle', 'edit_vehicle', 'suspend_driver', 'suspend_vehicle', 'view_assignment', 'manage_assignment'],
        'Park Manager' => ['create_operator', 'edit_operator', 'view_driver', 'view_vehicle', 'edit_vehicle', 'view_assignment', 'manage_assignment'],
        'Executive Viewer' => [],
        'Finance Administrator' => ['view_driver', 'view_vehicle', 'view_assignment', 'view_revenue_head', 'manage_revenue_head', 'view_fee_configuration', 'manage_fee_configuration'],
        'Revenue Officer' => ['view_driver', 'view_vehicle', 'view_assignment', 'view_revenue_head', 'view_fee_configuration'],
        'Auditor' => ['view_driver', 'view_vehicle', 'view_assignment', 'view_revenue_head', 'view_fee_configuration'],
        'Ticketing Officer' => ['view_driver', 'view_vehicle', 'view_assignment'],
        'Collection Agent' => ['view_driver', 'view_vehicle', 'view_assignment'],
        'Enforcement Officer' => ['view_driver', 'view_vehicle', 'view_assignment'],
        'Transport Operator' => ['view_driver', 'view_vehicle', 'view_assignment'],
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
        $permissions = collect(self::GRANTS)->flatten()->merge(collect(self::REGISTRY_GRANTS)->flatten())->merge(collect(self::TRANSPORT_GRANTS)->flatten())->merge(collect(self::TICKETING_GRANTS)->flatten())->merge(collect(self::PAYMENT_GRANTS)->flatten())->merge(collect(self::DASHBOARD_GRANTS)->flatten())->merge(collect(self::RECONCILIATION_GRANTS)->flatten())->merge(collect(self::CORRECTION_GRANTS)->flatten())->merge(collect(self::FIELD_GRANTS)->flatten())->reject(fn ($name) => $name === '*')->push('manage_users', 'manage_roles')->unique();
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        foreach (self::GRANTS as $name => $grants) {
            if ($grants !== ['*']) {
                $grants = array_merge($grants, self::REGISTRY_GRANTS[$name] ?? [], self::TRANSPORT_GRANTS[$name] ?? [], self::TICKETING_GRANTS[$name] ?? [], self::PAYMENT_GRANTS[$name] ?? [], self::DASHBOARD_GRANTS[$name] ?? [], self::RECONCILIATION_GRANTS[$name] ?? [], self::CORRECTION_GRANTS[$name] ?? [], self::FIELD_GRANTS[$name] ?? []);
            }
            Role::findOrCreate($name, 'web')->syncPermissions($grants === ['*'] ? Permission::where('guard_name', 'web')->get() : $grants);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
