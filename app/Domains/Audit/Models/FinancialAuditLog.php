<?php

namespace App\Domains\Audit\Models;

use App\Support\Models\RetainedFinancialModel;

class FinancialAuditLog extends RetainedFinancialModel
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'amount' => 'decimal:2', 'occurred_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        parent::booted();
        static::updating(fn () => throw new \LogicException('Financial audit is append-only.'));
    }
}
