<?php

namespace App\Domains\Finance\Models;

use App\Domains\Finance\Enums\RefundStatus;
use App\Domains\Payments\Models\Payment;
use App\Domains\Ticketing\Models\Ticket;
use App\Support\Models\RetainedFinancialModel;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends RetainedFinancialModel
{
    use HasUlids;

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'requested_at' => 'immutable_datetime', 'status' => RefundStatus::class, 'processed_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        parent::booted();
        static::updating(function ($record) {
            if (array_diff(array_keys($record->getDirty()), ['status', 'approved_by', 'rejected_by', 'provider_reference', 'processed_at', 'updated_at'])) {
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

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
