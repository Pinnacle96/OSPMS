<?php

namespace App\Domains\Enforcement\Models;

use App\Domains\Drivers\Models\Driver;
use App\Domains\Enforcement\Enums\InspectionResult;
use App\Domains\Operators\Models\Operator;
use App\Domains\Parks\Models\Park;
use App\Domains\System\Models\MediaAttachment;
use App\Domains\Ticketing\Models\Ticket;
use App\Domains\Vehicles\Models\Vehicle;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Inspection extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return ['result' => InspectionResult::class, 'latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'occurred_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
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

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function evidence()
    {
        return $this->morphMany(MediaAttachment::class, 'attachable');
    }

    public function violations()
    {
        return $this->hasMany(Violation::class);
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Inspection records are immutable.'));
        static::deleting(fn () => throw new \LogicException('Inspection history must be retained.'));
    }
}
