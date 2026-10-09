<?php

namespace App\Domains\Enforcement\Models;

use App\Domains\Drivers\Models\Driver;
use App\Domains\Enforcement\Enums\ViolationStatus;
use App\Domains\Operators\Models\Operator;
use App\Domains\Parks\Models\Park;
use App\Domains\System\Models\MediaAttachment;
use App\Domains\Vehicles\Models\Vehicle;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Violation extends Model
{
    use HasUlids;

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return ['status' => ViolationStatus::class, 'occurred_at' => 'immutable_datetime', 'issued_at' => 'immutable_datetime', 'resolved_at' => 'immutable_datetime'];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function park()
    {
        return $this->belongsTo(Park::class)->withTrashed();
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class)->withTrashed();
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class)->withTrashed();
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function evidence()
    {
        return $this->morphMany(MediaAttachment::class, 'attachable');
    }

    public function inspection()
    {
        return $this->belongsTo(Inspection::class);
    }

    protected static function booted(): void
    {
        static::updating(function ($r) {
            if (array_diff(array_keys($r->getDirty()), ['status', 'resolution', 'resolved_by', 'resolved_at', 'updated_at'])) {
                throw new \LogicException('Original operational observations are immutable.');
            }
            if ($r->getRawOriginal('resolved_at') && array_intersect(array_keys($r->getDirty()), ['resolution', 'resolved_by', 'resolved_at'])) {
                throw new \LogicException('The retained resolution is immutable.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Operational history must be retained.'));
    }
}
