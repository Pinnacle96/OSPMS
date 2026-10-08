<?php

namespace App\Domains\Reconciliation\Models;

use App\Domains\Finance\Models\FinancialTransaction;
use App\Domains\Finance\Models\SettlementItem;
use App\Domains\Identity\Models\User;
use App\Domains\Payments\Models\Payment;
use App\Domains\Reconciliation\Enums\ReconciliationItemStatus;
use App\Domains\Ticketing\Models\Ticket;
use App\Support\Models\RetainedFinancialModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationItem extends RetainedFinancialModel
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => ReconciliationItemStatus::class, 'expected_amount' => 'decimal:2', 'actual_amount' => 'decimal:2', 'difference_amount' => 'decimal:2', 'resolved_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        parent::booted();
        static::updating(function ($item) {
            if (array_diff(array_keys($item->getDirty()), ['status', 'resolution_note', 'resolved_by', 'resolved_at', 'updated_at'])) {
                throw new \LogicException('Reconciliation findings are immutable.');
            }
        });
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ReconciliationRun::class, 'reconciliation_run_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'financial_transaction_id');
    }

    public function settlementItem(): BelongsTo
    {
        return $this->belongsTo(SettlementItem::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
