<?php

namespace App\Domains\Reporting\Queries;

use App\Domains\Finance\Models\FinancialTransaction;
use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Parks\Models\Park;
use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\Queries\FinancialFilterOptions;
use App\Domains\Reporting\Services\DashboardDateRange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RevenueDashboardQuery
{
    public function get(User $user, array $filters, array $range, Lga|Park|null $record = null): ?array
    {
        $permission = $record instanceof Lga ? 'view_lga_revenue' : ($record instanceof Park ? 'view_park_revenue' : 'view_state_revenue');
        if ($record) {
            Gate::forUser($user)->authorize('view', $record);
        }
        if (! $user->can($permission)) {
            return null;
        }

        return DB::transaction(function () use ($user, $filters, $range, $record) {
            $scope = app(UserAccessScopeService::class);
            $ledger = $scope->scopeLedger(FinancialTransaction::query(), $user)->where('financial_transactions.currency', 'NGN');
            $payments = $scope->scopePayments(Payment::query(), $user)->where('payments.currency', 'NGN');
            foreach ([$ledger, $payments] as $query) {
                $query->whereHas('ticket', function ($tickets) use ($filters, $record) {
                    if ($record) {
                        $tickets->where($record instanceof Lga ? 'lga_id' : 'park_id', $record->id);
                    }
                    foreach (['lga_id', 'park_id', 'revenue_head_id'] as $key) {
                        if (! empty($filters[$key])) {
                            $tickets->where($key, $filters[$key]);
                        }
                    }
                });
            }
            if (! empty($filters['channel'])) {
                $payments->where('channel', $filters['channel']);
                $ledger->whereHas('payment', fn ($p) => $p->where('channel', $filters['channel']));
            }
            $selected = $this->period(clone $ledger, 'financial_transactions.occurred_at', $range);
            $attempts = $this->period(clone $payments, 'payments.initiated_at', $range);
            $aggregate = app(LedgerAggregateQuery::class);
            $today = CarbonImmutable::now(config('ospm.timezone'));
            $dates = app(DashboardDateRange::class);
            $summary = $aggregate->get(clone $selected)->first();
            $summary['today'] = $aggregate->get($this->period(clone $ledger, 'financial_transactions.occurred_at', $dates->between($today->toDateString(), $today->toDateString())))->first();
            $summary['month'] = $aggregate->get($this->period(clone $ledger, 'financial_transactions.occurred_at', $dates->between($today->startOfMonth()->toDateString(), $today->toDateString())))->first();
            $day = $dates->dayExpression($range, DB::getDriverName());
            $trend = $aggregate->get(clone $selected, ['date' => $day])->keyBy('date');
            $series = [];
            if ($trend->isNotEmpty()) {
                for ($dayDate = CarbonImmutable::parse($range['from']); $dayDate->toDateString() <= $range['to']; $dayDate = $dayDate->addDay()) {
                    $key = $dayDate->toDateString();
                    $series[] = $trend->get($key, ['date' => $key, 'gross' => '0.00', 'debits' => '0.00', 'net' => '0.00', 'transactions' => 0]);
                }
            }
            $grouped = [];
            foreach (['lga' => 'lgas', 'park' => 'parks', 'revenue_head' => 'revenue_heads'] as $dimension => $table) {
                $joined = (clone $selected)->join('tickets as revenue_tickets', 'revenue_tickets.id', '=', 'financial_transactions.ticket_id')->join($table.' as dimension', 'dimension.id', '=', 'revenue_tickets.'.$dimension.'_id');
                $rows = $aggregate->get($joined, ['id' => 'dimension.id', 'name' => 'dimension.name'])->sortByDesc('transactions')->values();
                $grouped['revenue_by_'.$dimension] = $rows->map(fn ($row) => [...$row, 'href' => $this->ledgerUrl($user, $filters, $range, $record, [$dimension.'_id' => $row['id']])])->all();
            }
            $counts = (clone $attempts)->select('status')->selectRaw('COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
            $statuses = collect(['successful', 'failed', 'pending', 'reversed', 'refunded'])->map(fn ($status) => ['name' => $status, 'count' => (int) ($counts[$status] ?? 0), 'href' => $this->paymentUrl($user, $filters, $range, $record, ['status' => $status])])->all();
            $channels = (clone $attempts)->select('channel')->selectRaw('COUNT(*) as total')->groupBy('channel')->orderBy('channel')->get()->map(fn ($row) => ['name' => $row->channel, 'count' => (int) $row->total, 'href' => $this->paymentUrl($user, $filters, $range, $record, ['channel' => $row->channel])])->all();
            $recent = (clone $selected)->with('payment')->orderByDesc('occurred_at')->orderByDesc('id')->limit(6)->get()->map(fn ($row) => [
                'reference' => $row->transaction_reference, 'direction' => $row->direction, 'type' => $row->transaction_type,
                'amount' => $row->amount, 'currency' => $row->currency, 'occurred_at' => $row->occurred_at,
                'href' => $user->can('view_financial_ledger') ? '/finance/ledger/'.$row->public_id : ($row->payment && $user->can('view_payment') ? '/payments/'.$row->payment->public_id : null),
            ])->all();

            return ['summary' => $summary, 'revenue_trend' => $series, ...$grouped,
                'payment_status' => $statuses, 'payment_channels' => $channels, 'recent_transactions' => $recent,
                'ledger_url' => $this->ledgerUrl($user, $filters, $range, $record),
                'payments_url' => $this->paymentUrl($user, $filters, $range, $record),
                'currency' => 'NGN', 'timezone' => config('ospm.timezone'), 'reconciliation_available' => false,
                ...app(FinancialFilterOptions::class)->get($user),
            ];
        });
    }

    private function period(Builder $query, string $column, array $range): Builder
    {
        return $query->where($column, '>=', $range['start_utc'])->where($column, '<', $range['end_utc']);
    }

    private function parameters(array $filters, array $range, Lga|Park|null $record, array $extra): array
    {
        $params = [...array_intersect_key($filters, array_flip(['lga_id', 'park_id', 'revenue_head_id', 'channel'])), 'from' => $range['from'], 'to' => $range['to'], 'timezone' => config('ospm.timezone'), 'currency' => 'NGN', ...$extra];
        if ($record) {
            $params[$record instanceof Lga ? 'lga_id' : 'park_id'] = $record->id;
        }

        return array_filter($params, fn ($value) => $value !== null && $value !== '');
    }

    private function ledgerUrl(User $user, array $filters, array $range, Lga|Park|null $record, array $extra = []): ?string
    {
        return $user->can('view_financial_ledger') ? '/finance/ledger?'.http_build_query($this->parameters($filters, $range, $record, $extra)) : null;
    }

    private function paymentUrl(User $user, array $filters, array $range, Lga|Park|null $record, array $extra = []): ?string
    {
        return $user->can('view_payment') ? '/payments?'.http_build_query($this->parameters($filters, $range, $record, $extra)) : null;
    }
}
