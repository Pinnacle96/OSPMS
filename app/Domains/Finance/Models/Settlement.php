<?php

namespace App\Domains\Finance\Models;

use App\Domains\Finance\Enums\SettlementStatus;
use App\Support\Models\RetainedFinancialModel;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Settlement extends RetainedFinancialModel
{
    use HasUlids;

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return ['status' => SettlementStatus::class, 'gross_amount' => 'decimal:2', 'provider_fees' => 'decimal:2', 'net_amount' => 'decimal:2', 'period_start' => 'immutable_datetime', 'period_end' => 'immutable_datetime', 'settled_at' => 'immutable_datetime', 'metadata' => 'array'];
    }

    protected static function booted(): void
    {
        parent::booted();
        static::updating(fn () => throw new \LogicException('Settlement evidence is immutable.'));
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function items(): HasMany
    {
        return $this->hasMany(SettlementItem::class);
    }
}
