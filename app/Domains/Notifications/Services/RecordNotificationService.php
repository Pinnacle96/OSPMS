<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Complaints\Models\Complaint;
use App\Domains\Drivers\Models\Driver;
use App\Domains\Identity\Models\User;
use App\Domains\Payments\Models\Payment;
use App\Domains\Reconciliation\Models\ReconciliationRun;
use App\Domains\Vehicles\Models\Vehicle;
use App\Notifications\RecordNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Ramsey\Uuid\Uuid;

class RecordNotificationService
{
    public const SUBJECTS = [
        'complaint' => [Complaint::class, '/complaints/', 'view'],
        'driver' => [Driver::class, '/drivers/', 'view'],
        'vehicle' => [Vehicle::class, '/vehicles/', 'view'],
        'payment' => [Payment::class, '/payments/', 'view'],
        'reconciliation' => [ReconciliationRun::class, '/finance/reconciliation/runs/', 'view'],
    ];

    public function send(?User $u, Model $r, string $kind, string $event, string $reference, string $title, string $class = RecordNotification::class): void
    {
        if (! $u) {
            return;
        }DB::transaction(function () use ($u, $r, $kind, $event, $reference, $title, $class) {
            $u = User::whereKey($u->id)->lockForUpdate()->first();
            if (! $u || $u->status->value !== 'active' || ! Gate::forUser($u)->allows(self::SUBJECTS[$kind][2], $r)) {
                return;
            }
            $id = Uuid::uuid5(Uuid::NAMESPACE_URL, 'ospm:'.$u->public_id.':'.$kind.':'.$r->public_id.':'.$event)->toString();
            if ($u->notifications()->whereKey($id)->exists()) {
                return;
            }
            $n = new $class($kind, $r->public_id, $reference, $title);
            $n->id = $id;
            $u->notify($n);
        }, 3);
    }

    public function safe(DatabaseNotification $n, User $u): array
    {
        $d = $n->data;
        $spec = self::SUBJECTS[$d['kind'] ?? ''] ?? null;
        $r = $spec ? $spec[0]::where('public_id', $d['subject'] ?? '')->first() : null;
        $allowed = $r && Gate::forUser($u)->allows($spec[2], $r);

        return ['id' => $n->id, 'title' => $allowed ? $d['title'] : 'Record unavailable in your current scope', 'reference' => $allowed ? $d['reference'] : null, 'href' => $allowed ? $spec[1].$r->public_id : null, 'read_at' => $n->read_at?->toIso8601String(), 'created_at' => $n->created_at->toIso8601String()];
    }
}
