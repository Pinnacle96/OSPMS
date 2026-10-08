<?php

namespace App\Domains\Operators\Models;

use App\Domains\Assignments\Models\DriverAssignment;
use App\Domains\Operators\Enums\OperatorStatus;
use App\Domains\Parks\Models\Park;
use App\Domains\Routes\Models\Route;
use App\Domains\System\Models\MediaAttachment;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Operator extends Model
{
    use HasUlids,SoftDeletes;

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return ['status' => OperatorStatus::class, 'registered_at' => 'datetime', 'approved_at' => 'datetime'];
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

    public function parks(): BelongsToMany
    {
        return $this->belongsToMany(Park::class, 'operator_park')->using(OperatorPark::class)->withPivot('status', 'approved_at', 'created_at');
    }

    public function routes(): BelongsToMany
    {
        return $this->belongsToMany(Route::class, 'operator_route')->using(OperatorRoute::class)->withPivot('park_id', 'status', 'approved_at', 'created_at');
    }
}
