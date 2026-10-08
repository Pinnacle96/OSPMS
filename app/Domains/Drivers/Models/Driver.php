<?php

namespace App\Domains\Drivers\Models;

use App\Domains\Assignments\Models\DriverAssignment;
use App\Domains\Drivers\Enums\DriverStatus;
use App\Domains\System\Models\MediaAttachment;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    use HasUlids,SoftDeletes;

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return ['status' => DriverStatus::class, 'registered_at' => 'datetime', 'approved_at' => 'datetime', 'licence_expiry' => 'date:Y-m-d'];
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
