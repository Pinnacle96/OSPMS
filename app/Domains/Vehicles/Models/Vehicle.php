<?php

namespace App\Domains\Vehicles\Models;

use App\Domains\Assignments\Models\DriverAssignment;
use App\Domains\System\Models\MediaAttachment;
use App\Domains\Vehicles\Enums\VehicleStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasUlids,SoftDeletes;

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return ['status' => VehicleStatus::class, 'registered_at' => 'datetime', 'approved_at' => 'datetime', 'roadworthiness_expiry' => 'date:Y-m-d', 'insurance_expiry' => 'date:Y-m-d', 'manufacture_year' => 'integer'];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DriverAssignment::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(MediaAttachment::class, 'attachable');
    }
}
