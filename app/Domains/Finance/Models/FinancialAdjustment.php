<?php

namespace App\Domains\Finance\Models;

use App\Domains\Finance\Enums\AdjustmentStatus;
use App\Domains\Finance\Enums\AdjustmentType;
use App\Support\Models\RetainedFinancialModel;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialAdjustment extends RetainedFinancialModel
{
    use HasUlids;

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'requested_at' => 'immutable_datetime', 'status' => AdjustmentStatus::class, 'adjustment_type' => AdjustmentType::class, 'approved_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        parent::booted();
        static::updating(function ($record) {
            if (array_diff(array_keys($record->getDirty()), ['status', 'approved_by', 'approved_at', 'updated_at'])) {
                throw new \LogicException('Financial request terms are immutable.');
            }
        });
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function originalTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'original_transaction_id');
    }
}
