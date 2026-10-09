<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Reporting\Reports;
use App\Domains\Reporting\Reports\BaseReport;

class ReportCatalog
{
    public const TYPES = ['daily-revenue' => Reports\DailyRevenueReport::class, 'monthly-revenue' => Reports\MonthlyRevenueReport::class, 'revenue-by-park' => Reports\RevenueByParkReport::class, 'revenue-by-lga' => Reports\RevenueByLgaReport::class, 'transactions' => Reports\TransactionReport::class, 'reconciliation' => Reports\ReconciliationReport::class, 'vehicles' => Reports\VehicleReport::class, 'drivers' => Reports\DriverReport::class, 'operators' => Reports\OperatorReport::class, 'incidents' => Reports\IncidentReport::class, 'complaints' => Reports\ComplaintReport::class];

    public function get(string $type): BaseReport
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return app(self::TYPES[$type]);
    }

    public function allowed(User $u, BaseReport $r, bool $export = false): bool
    {
        if ($u->status->value !== 'active' || ! $u->can('view_reports') || ($export && ! $u->can('export_reports'))) {
            return false;
        }
        $permissions = match ($r->family) {
            'revenue' => ['view_state_revenue', 'view_lga_revenue', 'view_park_revenue'],'payments' => ['view_payment'],'reconciliation' => ['view_reconciliation'],default => ['view_'.rtrim($r->family, 's')]
        };

        return collect($permissions)->contains(fn ($p) => $u->can($p));
    }

    public function authorize(User $u, BaseReport $r, bool $export = false): void
    {
        abort_unless($this->allowed($u, $r, $export), 403);
    }

    public function listing(User $u): array
    {
        return collect(array_keys(self::TYPES))->map(fn ($type) => $this->get($type))->filter(fn ($r) => $this->allowed($u, $r))->map(fn ($r) => ['type' => $r->type, 'title' => $r->title, 'basis' => $r->basis, 'can_export' => $this->allowed($u, $r, true)])->values()->all();
    }
}
