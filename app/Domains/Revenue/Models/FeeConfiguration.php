<?php

namespace App\Domains\Revenue\Models;

use App\Domains\Geography\Models\Lga;
use App\Domains\Parks\Models\Park;
use App\Domains\Revenue\Enums\FeeConfigurationStatus;
use App\Domains\Routes\Models\Route;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeConfiguration extends Model
{
    use HasUlids;

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return ['status' => FeeConfigurationStatus::class, 'amount' => 'decimal:2', 'effective_from' => 'datetime', 'effective_to' => 'datetime', 'priority' => 'integer'];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function revenueHead(): BelongsTo
    {
        return $this->belongsTo(RevenueHead::class);
    }

    public function lga(): BelongsTo
    {
        return $this->belongsTo(Lga::class);
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
