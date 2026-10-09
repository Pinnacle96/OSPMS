<?php

namespace App\Domains\Reporting\Queries;

use App\Domains\Complaints\Models\Complaint;
use App\Domains\Complaints\Services\ComplaintScope;
use App\Domains\Drivers\Models\Driver;
use App\Domains\Finance\Models\FinancialTransaction;
use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Models\User;
use App\Domains\Incidents\Models\Incident;
use App\Domains\Incidents\Services\OperationalScope;
use App\Domains\Operators\Models\Operator;
use App\Domains\Parks\Models\Park;
use App\Domains\Payments\Enums\PaymentStatus;
use App\Domains\Payments\Models\Payment;
use App\Domains\Reconciliation\Models\ReconciliationItem;
use App\Domains\Reconciliation\Services\ReconciliationAccess;
use App\Domains\Reporting\Reports\BaseReport;
use App\Domains\Reporting\Services\DashboardDateRange;
use App\Domains\Reporting\Services\ReportScope;
use App\Domains\Revenue\Models\RevenueHead;
use App\Domains\Ticketing\Models\Ticket;
use App\Domains\Vehicles\Models\Vehicle;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReportQuery
{
    public const MODELS = ['lga' => Lga::class, 'park' => Park::class, 'revenue_head' => RevenueHead::class, 'operator' => Operator::class, 'vehicle' => Vehicle::class, 'driver' => Driver::class];

    public function filterKeys(BaseReport $r): array
    {
        return ['from', 'to', 'lga', 'park', ...($this->financial($r) ? ['revenue_head'] : []), 'operator', 'vehicle', 'driver', 'status', ...($this->financial($r) ? ['channel'] : [])];
    }

    public function financial(BaseReport $r): bool
    {
        return in_array($r->family, ['revenue', 'payments', 'reconciliation']);
    }

    public function base(BaseReport $r, User $u): Builder
    {
        $s = app(ReportScope::class);

        return match ($r->family) {
            'revenue' => FinancialTransaction::where('financial_transactions.currency', 'NGN')->whereHas('ticket', fn ($q) => $s->tickets($q, $u)),
            'payments' => Payment::where('currency', 'NGN')->whereHas('ticket', fn ($q) => $s->tickets($q, $u)),
            'reconciliation' => app(ReconciliationAccess::class)->items(ReconciliationItem::query(), $u)->when($s->own($u), fn ($q) => $q->whereHas('ticket', fn ($t) => $s->tickets($t, $u)))->whereHas('run', fn ($q) => $q->whereIn('status', ['completed', 'completed_with_exceptions'])),
            'incidents' => app(OperationalScope::class)->query(Incident::query(), $u),
            'complaints' => app(ComplaintScope::class)->query(Complaint::query(), $u),
            'drivers' => $s->registry(Driver::withTrashed(), $u, 'drivers'),
            'vehicles' => $s->registry(Vehicle::withTrashed(), $u, 'vehicles'),
            'operators' => $s->registry(Operator::withTrashed(), $u, 'operators'),
        };
    }

    public function candidates(BaseReport $r, User $u, string $field): Builder
    {
        $class = self::MODELS[$field];
        $q = in_array($field, ['lga', 'park', 'operator', 'driver', 'vehicle']) ? $class::withTrashed() : $class::query();
        $base = $this->base($r, $u);
        $table = $base->getModel()->getTable();
        if ($this->financial($r)) {
            $tickets = Ticket::whereIn('id', $base->select($table.'.ticket_id'));

            return $q->whereIn('id', $tickets->select($field.'_id'));
        }
        if (in_array($r->family, ['incidents', 'complaints'])) {
            if ($field === 'lga') {
                return $q->whereIn('id', Park::withTrashed()->whereIn('id', $base->select($table.'.park_id'))->select('lga_id'));
            }

            return $q->whereIn('id', $base->select($table.'.'.$field.'_id'));
        }
        $self = match ($r->family) {
            'drivers' => 'driver','vehicles' => 'vehicle','operators' => 'operator'
        };
        if ($field === $self) {
            return $q->whereIn('id', $base->select($table.'.id'));
        }
        $a = app(ReportScope::class)->assignments($u)->whereIn($self.'_id', $base->select($table.'.id'));
        if ($r->family === 'operators' && in_array($field, ['park', 'lga'])) {
            $parks = app(ReportScope::class)->parks($u)->whereHas('operators', fn ($o) => $o->whereIn('operators.id', $base->select('operators.id'))->where('operator_park.status', 'active'));

            return $field === 'park' ? $q->whereIn('id', $parks->select('parks.id')) : $q->whereIn('id', $parks->select('parks.lga_id'));
        }

        return $field === 'lga' ? $q->whereIn('id', Park::withTrashed()->whereIn('id', $a->select('park_id'))->select('lga_id')) : $q->whereIn('id', $a->select($field.'_id'));
    }

    public function statuses(BaseReport $r, User $u): array
    {
        $cast = $r->family === 'revenue' ? PaymentStatus::class : $this->base($r, $u)->getModel()->getCasts()['status'];

        return array_column($cast::cases(), 'value');
    }

    public const CHANNELS = ['cashless_pos', 'transfer', 'ussd', 'gateway', 'demo', 'other'];

    public function options(BaseReport $r, User $u, array $criteria = [], string $search = ''): array
    {
        $out = [];
        foreach (array_intersect($r->filters(), array_keys(self::MODELS)) as $field) {
            $cols = match ($field) {
                'driver' => ['public_id', 'first_name', 'last_name'],'vehicle' => ['public_id', 'registration_number'],default => ['public_id', 'name']
            };
            $q = $this->candidates($r, $u, $field);
            if ($search !== '') {
                $names = match ($field) {
                    'driver' => ['first_name', 'last_name'],
                    'vehicle' => ['registration_number'],
                    default => ['name'],
                };
                $q->where(function ($q) use ($names, $search) {
                    foreach ($names as $name) {
                        $q->orWhere($name, 'like', '%'.$search.'%');
                    }
                });
            }
            $models = (clone $q)->orderBy('id')->limit(config('reports.filter_options_limit'))->get($cols);
            if (! empty($criteria[$field]) && ! $models->contains('public_id', $criteria[$field])) {
                $selected = $this->candidates($r, $u, $field)->where('public_id', $criteria[$field])->first($cols);
                if ($selected) {
                    $models->push($selected);
                }
            }
            $out[$field] = $models->map(fn ($m) => ['public_id' => $m->public_id, 'name' => match ($field) {
                'driver' => trim($m->first_name.' '.$m->last_name),'vehicle' => $m->registration_number,default => $m->name
            }])->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
        }
        $out['status'] = $this->statuses($r, $u);
        if ($this->financial($r)) {
            $out['channel'] = self::CHANNELS;
        }

        return $out;
    }

    public function query(BaseReport $r, User $u, array $f): Builder
    {
        $q = $this->base($r, $u);
        $table = $q->getModel()->getTable();
        $range = app(DashboardDateRange::class)->resolve($f);
        $ids = [];
        foreach (self::MODELS as $field => $class) {
            if (! empty($f[$field])) {
                $ids[$field.'_id'] = $class::withoutGlobalScopes()->where('public_id', $f[$field])->value('id');
            }
        }
        if ($r->family === 'reconciliation') {
            $q->whereHas('run', fn ($run) => $run->where('started_at', '>=', $range['start_utc'])->where('started_at', '<', $range['end_utc']));
        } else {
            $column = match ($r->family) {
                'revenue' => 'occurred_at','payments' => 'initiated_at','incidents' => 'occurred_at',default => 'created_at'
            };
            $q->where($table.'.'.$column, '>=', $range['start_utc'])->where($table.'.'.$column, '<', $range['end_utc']);
        }
        if ($this->financial($r)) {
            $q->when($ids, fn ($q) => $q->whereHas('ticket', fn ($t) => $t->where($ids)));
            if (! empty($f['status'])) {
                $r->family === 'revenue' ? $q->whereHas('payment', fn ($p) => $p->where('status', $f['status'])) : $q->where($table.'.status', $f['status']);
            }
            if (! empty($f['channel'])) {
                $r->family === 'payments' ? $q->where('channel', $f['channel']) : $q->whereHas('payment', fn ($p) => $p->where('channel', $f['channel']));
            }
        } else {
            if (! empty($f['status'])) {
                $q->where($table.'.status', $f['status']);
            }
            if (in_array($r->family, ['incidents', 'complaints'])) {
                foreach ($ids as $field => $id) {
                    if ($field === 'lga_id') {
                        $q->whereHas('park', fn ($p) => $p->withTrashed()->where('lga_id', $id));
                    } else {
                        $q->where($field, $id);
                    }
                }
            } else {
                $self = match ($r->family) {
                    'drivers' => 'driver_id','vehicles' => 'vehicle_id','operators' => 'operator_id'
                };
                if (isset($ids[$self])) {
                    $q->where($table.'.id', $ids[$self]);
                    unset($ids[$self]);
                }
                if ($ids) {
                    if ($r->family === 'operators' && ! isset($ids['driver_id']) && ! isset($ids['vehicle_id'])) {
                        $q->whereHas('parks', fn ($p) => $p->withTrashed()->where('operator_park.status', 'active')->when(isset($ids['park_id']), fn ($p) => $p->where('parks.id', $ids['park_id']))->when(isset($ids['lga_id']), fn ($p) => $p->where('lga_id', $ids['lga_id'])));
                    } else {
                        $q->whereHas('assignments', function ($a) use ($ids, $u) {
                            $a->whereIn('driver_assignments.id', app(ReportScope::class)->assignments($u)->select('driver_assignments.id'));
                            foreach ($ids as $field => $id) {
                                if ($field === 'lga_id') {
                                    $a->whereHas('park', fn ($p) => $p->withTrashed()->where('lga_id', $id));
                                } else {
                                    $a->where($field, $id);
                                }
                            }
                        });
                    }
                }
            }
        }

        return $q;
    }

    public function columns(BaseReport $r): array
    {
        $map = match ($r->family) {
            'revenue' => [$r->group === 'day' ? 'day' : ($r->group === 'month' ? 'month' : 'name') => $r->group === 'park' ? 'Park' : ($r->group === 'lga' ? 'LGA' : ucfirst($r->group)), 'transactions' => 'Ledger entries', 'gross' => 'Gross (NGN)', 'debits' => 'Debits (NGN)', 'net' => 'Net (NGN)'],
            'payments' => ['reference' => 'Payment reference', 'ticket' => 'Ticket', 'date' => 'Initiated (local)', 'status' => 'Payment status', 'channel' => 'Channel', 'amount' => 'Attempted (NGN)', 'lga' => 'Original LGA', 'park' => 'Original park'],
            'reconciliation' => ['reference' => 'Run reference', 'ticket' => 'Ticket', 'date' => 'Run started (local)', 'status' => 'Finding status', 'exception' => 'Exception', 'expected_amount' => 'Expected (NGN)', 'actual_amount' => 'Actual (NGN)', 'difference_amount' => 'Difference (NGN)'],
            'drivers' => ['reference' => 'Driver reference', 'name' => 'Driver', 'status' => 'Status', 'licence_expiry' => 'Licence expiry', 'date' => 'Registered record (local)'],
            'vehicles' => ['reference' => 'Vehicle reference', 'name' => 'Registration', 'status' => 'Status', 'vehicle_type' => 'Type', 'make' => 'Make', 'model' => 'Model', 'roadworthiness_expiry' => 'Roadworthiness expiry', 'insurance_expiry' => 'Insurance expiry', 'date' => 'Registered record (local)'],
            'operators' => ['reference' => 'Operator reference', 'name' => 'Operator', 'status' => 'Status', 'date' => 'Registered record (local)'],
            'incidents' => ['reference' => 'Incident reference', 'category' => 'Category', 'status' => 'Status', 'park' => 'Park', 'operator' => 'Named operator', 'date' => 'Occurred (local)', 'resolved_at' => 'Resolved (local)'],
            'complaints' => ['reference' => 'Complaint reference', 'category' => 'Category', 'status' => 'Status', 'source' => 'Source', 'park' => 'Park', 'assignee' => 'Assigned officer', 'date' => 'Submitted (local)', 'resolved_at' => 'Resolved (local)'],
        };

        return collect($map)->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'type' => in_array($key, ['gross', 'debits', 'net', 'amount', 'expected_amount', 'actual_amount', 'difference_amount']) ? 'money' : ($key === 'transactions' ? 'integer' : 'text')])->values()->all();
    }

    private function decimal(Builder $q, array $cols): array
    {
        $sum = 'SUM';
        if (DB::getDriverName() === 'sqlite') {
            DB::connection()->getPdo()->sqliteCreateAggregate('ospm_report_sum', fn ($v, $n, $x) => (string) BigDecimal::of($v ?? '0')->plus((string) ($x ?? '0')), fn ($v, $n) => (string) BigDecimal::of($v ?? '0')->toScale(2), 1);
            $sum = 'ospm_report_sum';
        }
        $q->selectRaw('COUNT(*) as records');
        foreach ($cols as $c) {
            $q->selectRaw($sum.'('.$c.') as '.$c);
        }$v = $q->first();
        $out = ['records' => (int) $v->getRawOriginal('records')];
        foreach ($cols as $c) {
            $out[$c] = (string) BigDecimal::of((string) ($v->getRawOriginal($c) ?? '0'))->toScale(2);
        }

        return $out;
    }

    public function summary(BaseReport $r, User $u, array $f): array
    {
        $q = $this->query($r, $u, $f);
        $v = match ($r->family) {
            'revenue' => app(LedgerAggregateQuery::class)->get($q)->first(),'payments' => $this->decimal($q, ['amount']),'reconciliation' => $this->decimal($q, ['expected_amount', 'actual_amount', 'difference_amount']),default => ['records' => $q->count()]
        };

        return collect($v)->map(fn ($value, $key) => ['key' => $key, 'label' => match ($key) {
            'amount' => 'Attempted amount','transactions' => 'Ledger entries','records' => $r->family === 'reconciliation' ? 'Retained findings' : 'Records',default => ucfirst(str_replace('_', ' ', $key))
        }, 'value' => $value, 'type' => in_array($key, ['records', 'transactions']) ? 'integer' : 'money'])->values()->all();
    }

    public function grouped(BaseReport $r, User $u, array $f)
    {
        $q = $this->query($r, $u, $f);
        $range = app(DashboardDateRange::class)->resolve($f);
        $day = app(DashboardDateRange::class)->dayExpression($range, DB::getDriverName());
        if ($r->group === 'day') {
            $groups = ['day' => $day];
        } elseif ($r->group === 'month') {
            $groups = ['month' => DB::getDriverName() === 'mysql' ? "DATE_FORMAT(($day), '%Y-%m')" : "SUBSTR(($day),1,7)"];
        } else {
            $table = $r->group === 'park' ? 'parks' : 'lgas';
            $q->join('tickets as report_tickets', 'report_tickets.id', '=', 'financial_transactions.ticket_id')->join($table.' as dimension', 'dimension.id', '=', 'report_tickets.'.$r->group.'_id');
            $groups = ['name' => 'dimension.name', 'group_id' => 'dimension.id'];
        }

        return app(LedgerAggregateQuery::class)->get($q, $groups)->sortBy($r->group === 'day' ? 'day' : ($r->group === 'month' ? 'month' : 'name'))->map(fn ($row) => array_diff_key($row, ['group_id' => true]))->values();
    }

    public function eager(BaseReport $r, Builder $q): Builder
    {
        return match ($r->family) {
            'payments' => $q->with(['ticket.park', 'ticket.lga']),'reconciliation' => $q->with(['run', 'ticket']),'incidents' => $q->with(['park', 'operator']),'complaints' => $q->with(['park', 'assignee']),default => $q
        };
    }

    public function row(BaseReport $r, $v): array
    {
        $time = fn ($x) => $x?->setTimezone(config('ospm.timezone'))->format('Y-m-d H:i:s');

        return match ($r->family) {
            'payments' => ['reference' => $v->payment_reference, 'ticket' => $v->ticket?->ticket_reference, 'date' => $time($v->initiated_at), 'status' => $v->status->value, 'channel' => $v->channel, 'amount' => $v->amount, 'lga' => $v->ticket?->lga?->name, 'park' => $v->ticket?->park?->name],
            'reconciliation' => ['reference' => $v->run->reconciliation_reference, 'ticket' => $v->ticket?->ticket_reference ?? 'Unlinked finding', 'date' => $time($v->run->started_at), 'status' => $v->status->value, 'exception' => $v->exception_type ?? '—', 'expected_amount' => $v->expected_amount, 'actual_amount' => $v->actual_amount, 'difference_amount' => $v->difference_amount],
            'drivers' => ['reference' => $v->driver_number, 'name' => trim($v->first_name.' '.$v->last_name), 'status' => $v->status->value, 'licence_expiry' => $v->licence_expiry?->format('Y-m-d'), 'date' => $time($v->created_at)],
            'vehicles' => ['reference' => $v->vehicle_number, 'name' => $v->registration_number, 'status' => $v->status->value, 'vehicle_type' => $v->vehicle_type, 'make' => $v->make, 'model' => $v->model, 'roadworthiness_expiry' => $v->roadworthiness_expiry?->format('Y-m-d'), 'insurance_expiry' => $v->insurance_expiry?->format('Y-m-d'), 'date' => $time($v->created_at)],
            'operators' => ['reference' => $v->operator_number, 'name' => $v->name, 'status' => $v->status->value, 'date' => $time($v->created_at)],
            'incidents' => ['reference' => $v->incident_reference, 'category' => $v->category, 'status' => $v->status->value, 'park' => $v->park?->name ?? 'Unlocated', 'operator' => $v->operator?->name ?? 'Not named', 'date' => $time($v->occurred_at), 'resolved_at' => $time($v->resolved_at)],
            'complaints' => ['reference' => $v->complaint_reference, 'category' => $v->category, 'status' => $v->status->value, 'source' => $v->source->value, 'park' => $v->park?->name ?? 'Unlocated', 'assignee' => $v->assignee?->name ?? 'Unassigned', 'date' => $time($v->created_at), 'resolved_at' => $time($v->resolved_at)],
        };
    }

    public function rows(BaseReport $r, User $u, array $f): iterable
    {
        if ($r->family === 'revenue') {
            yield from $this->grouped($r, $u, $f);

            return;
        }
        $q = $this->eager($r, $this->query($r, $u, $f));
        foreach ($q->lazyById(500) as $v) {
            yield $this->row($r, $v);
        }
    }
}
