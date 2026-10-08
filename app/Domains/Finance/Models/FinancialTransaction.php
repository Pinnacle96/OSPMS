<?php

namespace App\Domains\Finance\Models;

use App\Domains\Payments\Models\Payment;
use App\Domains\Ticketing\Models\Ticket;
use App\Support\Models\RetainedFinancialModel;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialTransaction extends RetainedFinancialModel
{
    use HasUlids;

    public $timestamps = false;

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'occurred_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'metadata' => 'array'];
    }

    protected static function booted(): void
    {
        parent::booted();
        static::updating(fn () => throw new \LogicException('Ledger entries are append-only.'));
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

    public function parentTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_transaction_id');
    }
}
