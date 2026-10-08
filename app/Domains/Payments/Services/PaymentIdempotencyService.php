<?php

namespace App\Domains\Payments\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Payments\Models\Payment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentIdempotencyService
{
    public function execute(User $actor, string $key, string $operation, array $request, callable $work): Payment
    {
        if (! preg_match('/\A[a-f0-9]{64}\z/D', $key)) {
            throw ValidationException::withMessages(['idempotency_key' => 'Use a fresh payment confirmation.']);
        }
        $hash = hash('sha256', json_encode($request, JSON_THROW_ON_ERROR));
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(function () use ($actor, $key, $operation, $hash, $work) {
                    $existing = DB::table('idempotency_keys')->where('idempotency_key', $key)->first();
                    if ($existing) {
                        $existing = DB::table('idempotency_keys')->where('id', $existing->id)->lockForUpdate()->first();
                        if ((int) $existing->user_id !== $actor->id || $existing->operation !== $operation || ! hash_equals($existing->request_hash, $hash)) {
                            throw ValidationException::withMessages(['idempotency_key' => 'This confirmation was already used for a different request. Refresh and try again.']);
                        }
                        $payload = json_decode($existing->response_payload, true, 512, JSON_THROW_ON_ERROR);

                        return Payment::findOrFail($payload['payment_id']);
                    }
                    $id = DB::table('idempotency_keys')->insertGetId(['idempotency_key' => $key, 'operation' => $operation, 'user_id' => $actor->id, 'request_hash' => $hash, 'expires_at' => now()->addDay(), 'created_at' => now()]);
                    $payment = $work();
                    DB::table('idempotency_keys')->where('id', $id)->update(['response_status' => 303, 'response_payload' => json_encode(['payment_id' => $payment->id], JSON_THROW_ON_ERROR)]);

                    return $payment;
                }, 3);
            } catch (UniqueConstraintViolationException $error) {
                if ($attempt === 2) {
                    throw $error;
                }
            }
        }
        throw new \LogicException('Financial identifier retry exhausted.');
    }
}
