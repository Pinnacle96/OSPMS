<?php

namespace App\Domains\Payments\Models;

use App\Domains\Payments\Enums\PaymentStatus;
use App\Domains\Ticketing\Models\Ticket;
use App\Support\Models\RetainedFinancialModel;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends RetainedFinancialModel
{
    use HasUlids;

    protected $guarded = ['id', 'public_id'];

    protected $hidden = ['idempotency_key', 'provider_metadata'];

    protected function casts(): array
    {
        return ['status' => PaymentStatus::class, 'amount' => 'decimal:2', 'initiated_at' => 'immutable_datetime', 'paid_at' => 'immutable_datetime', 'failed_at' => 'immutable_datetime', 'reversed_at' => 'immutable_datetime', 'provider_metadata' => 'array'];
    }

    protected static function booted(): void
    {
        parent::booted();
        static::updating(function (Payment $p) {
            if (array_diff(array_keys($p->getDirty()), ['status', 'paid_at', 'failed_at', 'reversed_at', 'provider_reference', 'provider_metadata', 'updated_at'])) {
                throw new \LogicException('Payment terms are immutable.');
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

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }
}
