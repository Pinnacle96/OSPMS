<?php

namespace App\Domains\Reconciliation\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Reconciliation\Enums\ReconciliationRunStatus;
use App\Support\Models\RetainedFinancialModel;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReconciliationRun extends RetainedFinancialModel
{
    use HasUlids;

    public $timestamps = false;

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return ['status' => ReconciliationRunStatus::class, 'period_start' => 'immutable_datetime', 'period_end' => 'immutable_datetime', 'started_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime', 'summary' => 'array'];
    }

    protected static function booted(): void
    {
        parent::booted();
        static::updating(function ($run) {
            if (in_array($run->getRawOriginal('status'), ['completed', 'completed_with_exceptions']) && $run->isDirty()) {
                throw new \LogicException('Completed reconciliation snapshots are immutable.');
            }
            if (array_diff(array_keys($run->getDirty()), ['status', 'summary', 'completed_at'])) {
                throw new \LogicException('Run criteria are immutable.');
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

    public function items(): HasMany
    {
        return $this->hasMany(ReconciliationItem::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }
}
