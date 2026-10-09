<?php

namespace App\Domains\Enforcement\Services;

use App\Domains\Assignments\Models\DriverAssignment;
use App\Domains\Drivers\Models\Driver;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Operators\Models\Operator;
use App\Domains\Vehicles\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FieldLookupService
{
    public const MODELS = ['drivers' => Driver::class, 'vehicles' => Vehicle::class, 'operators' => Operator::class];

    public function assignments(User $u): Builder
    {
        return app(UserAccessScopeService::class)->scopeAssignments(DriverAssignment::query(), $u);
    }

    public function current(User $u): Builder
    {
        return $this->assignments($u)->where('status', 'active')->where('starts_at', '<=', now())->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->whereHas('driver')->whereHas('vehicle')->whereHas('operator')->whereHas('park');
    }

    public function query(User $u, string $kind): Builder
    {
        $class = self::MODELS[$kind];
        $q = $class::query();
        $scope = app(UserAccessScopeService::class);

        return $kind === 'operators' ? $scope->scopeOperators($q, $u) : $q->whereHas('assignments', fn ($a) => $scope->scopeAssignments($a, $u));
    }

    public function authorize(User $u, Model $record, string $kind): void
    {
        abort_unless($this->query($u, $kind)->whereKey($record->id)->exists(), 403);
    }

    public function search(User $u, string $kind, array $filters)
    {
        $q = $this->query($u, $kind);
        $term = $filters['search'] ?? '';
        if ($term === '') {
            $q->whereRaw('1=0');
        } else {
            $q->where(function ($q) use ($kind, $term) {
                $s = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
                foreach (match ($kind) {
                    'drivers' => ['driver_number', 'first_name', 'last_name'],'vehicles' => ['registration_number', 'vehicle_number'],default => ['operator_number', 'name']
                } as $column) {
                    $q->orWhereRaw($column." LIKE ? ESCAPE '!'", [$s]);
                }
            });
        }
        $sort = match ($kind) {
            'drivers' => 'driver_number','vehicles' => 'registration_number',default => 'name'
        };

        return $q->orderBy($sort)->orderBy('id')->paginate(15)->withQueryString()->through(fn ($r) => $this->safe($r, $kind));
    }

    public function safe(Model $r, string $kind): array
    {
        return ['public_id' => $r->public_id, 'name' => match ($kind) {
            'drivers' => trim($r->first_name.' '.$r->last_name),'vehicles' => $r->registration_number,default => $r->name
        }, 'reference' => match ($kind) {
            'drivers' => $r->driver_number,'vehicles' => $r->vehicle_number,default => $r->operator_number
        }, 'status' => $r->status->value, ...match ($kind) {
            'drivers' => ['licence_expiry' => $r->licence_expiry?->format('Y-m-d')],'vehicles' => ['vehicle_type' => $r->vehicle_type, 'make' => $r->make, 'model' => $r->model, 'roadworthiness_expiry' => $r->roadworthiness_expiry?->format('Y-m-d'), 'insurance_expiry' => $r->insurance_expiry?->format('Y-m-d')],default => []
        }];
    }

    public function assignment(DriverAssignment $a): array
    {
        return ['context' => implode(':', [$a->driver->public_id, $a->vehicle->public_id, $a->operator->public_id, $a->park->public_id]), 'driver' => $this->safe($a->driver, 'drivers'), 'vehicle' => $this->safe($a->vehicle, 'vehicles'), 'operator' => $this->safe($a->operator, 'operators'), 'park' => ['public_id' => $a->park->public_id, 'name' => $a->park->name], 'status' => $a->status->value, 'starts_at' => $a->starts_at, 'ends_at' => $a->ends_at];
    }

    public function related(User $u, Model $r, string $kind)
    {
        $foreign = match ($kind) {
            'drivers' => 'driver_id','vehicles' => 'vehicle_id',default => 'operator_id'
        };

        return $this->assignments($u)->where($foreign, $r->id)->whereHas('driver')->whereHas('vehicle')->whereHas('operator')->whereHas('park')->with(['driver', 'vehicle', 'operator', 'park'])->orderByDesc('starts_at')->paginate(5)->withQueryString()->through(fn ($a) => $this->assignment($a));
    }

    public function contexts(User $u, ?string $subject = null, ?string $publicId = null)
    {
        $q = $this->current($u)->with(['driver', 'vehicle', 'operator', 'park']);
        if ($subject && $publicId) {
            $q->whereHas(match ($subject) {
                'drivers' => 'driver','vehicles' => 'vehicle',default => 'operator'
            }, fn ($r) => $r->where('public_id', $publicId));
        }

        return $q->orderBy('id')->limit(200)->get()->map(fn ($a) => $this->assignment($a));
    }
}
