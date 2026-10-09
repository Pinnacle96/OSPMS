<?php

namespace App\Domains\Enforcement\Services;

use App\Domains\Assignments\Models\DriverAssignment;
use App\Domains\Drivers\Models\Driver;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Operators\Models\Operator;
use App\Domains\Parks\Models\Park;
use App\Domains\Ticketing\Models\Ticket;
use App\Domains\Ticketing\Services\TicketVerificationService;
use App\Domains\Vehicles\Models\Vehicle;
use Illuminate\Validation\ValidationException;

class InspectionContextService
{
    public function authorizeContext(User $actor, array $input): void
    {
        if ($input['ticket'] ?? null) {
            $allowed = app(UserAccessScopeService::class)->scopeTickets(Ticket::query(), $actor)->where('public_id', $input['ticket'])->exists();
        } else {
            $parts = explode(':', $input['context'] ?? '');
            $allowed = count($parts) === 4 && app(FieldLookupService::class)->assignments($actor)
                ->whereHas('driver', fn ($q) => $q->where('public_id', $parts[0]))
                ->whereHas('vehicle', fn ($q) => $q->where('public_id', $parts[1]))
                ->whereHas('operator', fn ($q) => $q->where('public_id', $parts[2]))
                ->whereHas('park', fn ($q) => $q->where('public_id', $parts[3]))->exists();
        }
        if (! $allowed) {
            $this->fail('This record is unavailable in your current scope.');
        }
    }

    public function resolve(User $actor, array $input, bool $lock = false): array
    {
        if ($input['ticket'] ?? null) {
            $t = app(UserAccessScopeService::class)->scopeTickets(Ticket::query(), $actor)->where('public_id', $input['ticket'])->when($lock, fn ($q) => $q->lockForUpdate())->first();
            if (! $t) {
                $this->fail('The ticket is unavailable in your assigned scope.');
            }

            return ['ticket_id' => $t->id, 'park_id' => $t->park_id, 'operator_id' => $t->operator_id, 'driver_id' => $t->driver_id, 'vehicle_id' => $t->vehicle_id, 'ticket_valid' => app(TicketVerificationService::class)->safe($t)['valid'], 'reference' => $t->ticket_reference];
        }
        $parts = explode(':', $input['context'] ?? '');
        if (count($parts) !== 4) {
            $this->fail('Choose a current operating context.');
        }
        $q = app(FieldLookupService::class)->current($actor)->whereHas('driver', fn ($q) => $q->where('public_id', $parts[0]))->whereHas('vehicle', fn ($q) => $q->where('public_id', $parts[1]))->whereHas('operator', fn ($q) => $q->where('public_id', $parts[2]))->whereHas('park', fn ($q) => $q->where('public_id', $parts[3]));
        $a = $q->when($lock, fn ($q) => $q->lockForUpdate())->first();
        if (! $a) {
            $this->fail('This operating context changed or is outside your scope. Refresh the selection.');
        }

        return ['ticket_id' => null, 'park_id' => $a->park_id, 'operator_id' => $a->operator_id, 'driver_id' => $a->driver_id, 'vehicle_id' => $a->vehicle_id, 'ticket_valid' => null, 'reference' => $a->vehicle->registration_number.' · '.$a->park->name];
    }

    public function canPass(array $context, bool $lock): bool
    {
        if ($context['ticket_valid'] === false || ! DriverAssignment::where(array_intersect_key($context, array_flip(['driver_id', 'vehicle_id', 'operator_id', 'park_id'])))->where('status', 'active')->where('starts_at', '<=', now())->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->when($lock, fn ($q) => $q->lockForUpdate())->exists()) {
            return false;
        }
        foreach (['driver_id' => [Driver::class, 'drivers'], 'vehicle_id' => [Vehicle::class, 'vehicles'], 'operator_id' => [Operator::class, 'operators']] as $column => [$class,$kind]) {
            $r = $class::whereKey($context[$column])->when($lock, fn ($q) => $q->lockForUpdate())->first();
            if (! $r || app(ComplianceSummaryService::class)->get($r, $kind, true)['status'] !== 'clear') {
                return false;
            }
        }

        return Park::whereKey($context['park_id'])->when($lock, fn ($q) => $q->lockForUpdate())->first()?->status->value === 'active';
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['context' => $message]);
    }
}
