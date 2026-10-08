<?php

namespace App\Domains\Assignments\Models;

use App\Domains\Assignments\Enums\AssignmentStatus;
use App\Domains\Drivers\Models\Driver;
use App\Domains\Operators\Models\Operator;
use App\Domains\Parks\Models\Park;
use App\Domains\Routes\Models\Route;
use App\Domains\Vehicles\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverAssignment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => AssignmentStatus::class, 'is_primary' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    public function park(): BelongsTo
    {
        return $this->belongsTo(Park::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }
}
