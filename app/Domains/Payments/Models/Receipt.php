<?php

namespace App\Domains\Payments\Models;

use App\Domains\Ticketing\Models\Ticket;
use App\Support\Models\RetainedFinancialModel;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends RetainedFinancialModel
{
    use HasUlids;

    public $timestamps = false;

    protected $guarded = ['id', 'public_id'];

    protected $hidden = ['verification_token', 'rendered_file_path'];

    protected function casts(): array
    {
        return ['issued_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        parent::booted();
        static::updating(fn () => throw new \LogicException('Canonical receipts are immutable.'));
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

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
