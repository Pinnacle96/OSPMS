<?php

namespace App\Domains\Ticketing\Models;

use App\Domains\Drivers\Models\Driver;
use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Models\User;
use App\Domains\Operators\Models\Operator;
use App\Domains\Parks\Models\Park;
use App\Domains\Payments\Models\Payment;
use App\Domains\Revenue\Models\FeeConfiguration;
use App\Domains\Revenue\Models\RevenueHead;
use App\Domains\Routes\Models\Route;
use App\Domains\Ticketing\Enums\TicketPaymentStatus;
use App\Domains\Ticketing\Enums\TicketStatus;
use App\Domains\Vehicles\Models\Vehicle;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Ticket extends Model
{
    use HasUlids;

    protected $guarded = ['id', 'public_id'];

    protected $hidden = ['verification_token'];

    protected function casts(): array
    {
        return ['ticket_status' => TicketStatus::class, 'payment_status' => TicketPaymentStatus::class, 'amount' => 'decimal:2', 'issued_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'context_snapshot' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(function (Ticket $ticket) {
            if (array_diff(array_keys($ticket->getDirty()), ['ticket_status', 'payment_status', 'updated_at'])) {
                throw new LogicException('Issued ticket terms and context are immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Tickets must be retained; use an authorized status transition.'));
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function revenueHead(): BelongsTo
    {
        return $this->belongsTo(RevenueHead::class)->withTrashed();
    }

    public function feeConfiguration(): BelongsTo
    {
        return $this->belongsTo(FeeConfiguration::class);
    }

    public function lga(): BelongsTo
    {
        return $this->belongsTo(Lga::class)->withTrashed();
    }

    public function park(): BelongsTo
    {
        return $this->belongsTo(Park::class)->withTrashed();
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class)->withTrashed();
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class)->withTrashed();
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class)->withTrashed();
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
