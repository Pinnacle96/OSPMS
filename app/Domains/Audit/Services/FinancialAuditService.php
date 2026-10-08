<?php

namespace App\Domains\Audit\Services;

use App\Domains\Audit\Models\FinancialAuditLog;
use App\Domains\Identity\Models\User;
use App\Domains\Payments\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FinancialAuditService
{
    public function hash(array $event): string
    {
        $normalize = function ($value) use (&$normalize) {
            if (! is_array($value)) {
                return $value;
            } if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($normalize, $value);
        };

        return hash('sha256', json_encode($normalize($event), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public function record(User $actor, Payment $payment, string $type, array $payload = []): FinancialAuditLog
    {
        return $this->recordEntity($actor, $payment, $type, $payment->payment_reference, $payment->amount, $payment->currency, $payload);
    }

    public function recordEntity(User $actor, Model $entity, string $type, string $reference, ?string $amount = null, ?string $currency = null, array $payload = []): FinancialAuditLog
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Financial audit requires its business transaction.');
        }
        // Existing retained identity row serializes the chain, including the first event.
        DB::table('users')->orderBy('id')->lockForUpdate()->firstOrFail();
        $previous = FinancialAuditLog::orderByDesc('id')->lockForUpdate()->first();
        $event = ['event_id' => (string) Str::ulid(), 'actor_user_id' => $actor->id, 'event_type' => $type, 'entity_type' => $entity::class, 'entity_id' => $entity->id, 'business_reference' => $reference, 'amount' => $amount, 'currency' => $currency, 'ip_address' => app()->runningInConsole() ? null : request()->ip(), 'user_agent' => app()->runningInConsole() ? null : request()->userAgent(), 'payload' => $payload, 'previous_hash' => $previous?->entry_hash, 'occurred_at' => now()->format('Y-m-d H:i:s')];

        return FinancialAuditLog::create($event + ['entry_hash' => $this->hash($event)]);
    }
}
