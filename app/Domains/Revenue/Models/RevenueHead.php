<?php

namespace App\Domains\Revenue\Models;

use App\Domains\Revenue\Enums\RevenueFrequency;
use App\Domains\Revenue\Enums\RevenueHeadStatus;
use App\Domains\Ticketing\Models\Ticket;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RevenueHead extends Model
{
    use HasUlids,SoftDeletes;

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return ['status' => RevenueHeadStatus::class, 'frequency' => RevenueFrequency::class];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function fees(): HasMany
    {
        return $this->hasMany(FeeConfiguration::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
