<?php

namespace App\Domains\Finance\Models;

use App\Support\Models\RetainedFinancialModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettlementItem extends RetainedFinancialModel
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        parent::booted();
        static::updating(fn () => throw new \LogicException('Settlement evidence is immutable.'));
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'financial_transaction_id');
    }
}
