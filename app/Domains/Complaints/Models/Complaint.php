<?php

namespace App\Domains\Complaints\Models;

use App\Domains\Complaints\Enums\ComplaintSource;
use App\Domains\Complaints\Enums\ComplaintStatus;
use App\Domains\Drivers\Models\Driver;
use App\Domains\Identity\Models\User;
use App\Domains\Operators\Models\Operator;
use App\Domains\Parks\Models\Park;
use App\Domains\System\Models\MediaAttachment;
use App\Domains\Vehicles\Models\Vehicle;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    use HasUlids;

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return ['status' => ComplaintStatus::class, 'source' => ComplaintSource::class, 'resolved_at' => 'immutable_datetime'];
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

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by')->withTrashed();
    }

    public function notes()
    {
        return $this->hasMany(ComplaintNote::class);
    }

    public function evidence()
    {
        return $this->morphMany(MediaAttachment::class, 'attachable');
    }

    protected static function booted(): void
    {
        static::updating(function ($r) {
            if (array_diff(array_keys($r->getDirty()), ['status', 'assigned_to', 'resolution', 'resolved_at', 'updated_at'])) {
                throw new \LogicException('Original complaint reports are immutable.');
            }if ($r->getRawOriginal('resolved_at') && array_intersect(array_keys($r->getDirty()), ['assigned_to', 'resolution', 'resolved_at'])) {
                throw new \LogicException('Retained complaint resolution is immutable.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Complaint history must be retained.'));
    }
}
