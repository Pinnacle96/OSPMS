<?php

namespace App\Domains\Parks\Models;

use App\Domains\Assignments\Models\DriverAssignment;
use App\Domains\Geography\Models\Lga;
use App\Domains\Operators\Models\Operator;
use App\Domains\Operators\Models\OperatorPark;
use App\Domains\Parks\Enums\ParkStatus;
use App\Domains\Routes\Models\ParkRoute;
use App\Domains\Routes\Models\Route;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Park extends Model
{
    use HasUlids, SoftDeletes;

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return ['status' => ParkStatus::class, 'activated_at' => 'datetime', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7'];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function lga(): BelongsTo
    {
        return $this->belongsTo(Lga::class);
    }

    public function routes(): BelongsToMany
    {
        return $this->belongsToMany(Route::class, 'park_route')->using(ParkRoute::class)->withPivot('id', 'status', 'created_at');
    }

    public function operators(): BelongsToMany
    {
        return $this->belongsToMany(Operator::class, 'operator_park')->using(OperatorPark::class)->withPivot('status', 'approved_at', 'created_at');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DriverAssignment::class);
    }
}
