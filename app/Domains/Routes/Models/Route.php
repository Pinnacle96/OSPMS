<?php

namespace App\Domains\Routes\Models;

use App\Domains\Operators\Models\Operator;
use App\Domains\Operators\Models\OperatorRoute;
use App\Domains\Parks\Models\Park;
use App\Domains\Routes\Enums\RouteStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Route extends Model
{
    use HasUlids, SoftDeletes;

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return ['status' => RouteStatus::class];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function parks(): BelongsToMany
    {
        return $this->belongsToMany(Park::class, 'park_route')->using(ParkRoute::class)->withPivot('id', 'status', 'created_at');
    }

    public function operators(): BelongsToMany
    {
        return $this->belongsToMany(Operator::class, 'operator_route')->using(OperatorRoute::class)->withPivot('park_id', 'status', 'approved_at', 'created_at');
    }
}
