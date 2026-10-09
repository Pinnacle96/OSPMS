<?php

namespace App\Support;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmedActionService
{
    public function execute(?User $actor, string $key, string $operation, array $request, string $model, callable $work): Model
    {
        if (! preg_match('/\A[a-f0-9]{64}\z/D', $key)) {
            throw ValidationException::withMessages(['idempotency_key' => 'Refresh to obtain a fresh confirmation.']);
        }
        ksort($request);
        $hash = hash('sha256', json_encode($request, JSON_THROW_ON_ERROR));
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(function () use ($actor, $key, $operation, $hash, $model, $work) {
                    $existing = DB::table('idempotency_keys')->where('idempotency_key', $key)->lockForUpdate()->first();
                    if ($existing) {
                        if ((int) $existing->user_id !== (int) $actor?->id || $existing->operation !== $operation || ! hash_equals($existing->request_hash, $hash)) {
                            throw ValidationException::withMessages(['idempotency_key' => 'This confirmation was used for a different request. Refresh and try again.']);
                        }

                        return $model::findOrFail(json_decode($existing->response_payload, true, 512, JSON_THROW_ON_ERROR)['id']);
                    }
                    $id = DB::table('idempotency_keys')->insertGetId(['idempotency_key' => $key, 'operation' => $operation, 'user_id' => $actor?->id, 'request_hash' => $hash, 'expires_at' => now()->addDay(), 'created_at' => now()]);
                    $record = $work();
                    DB::table('idempotency_keys')->where('id', $id)->update(['response_status' => 303, 'response_payload' => json_encode(['id' => $record->id], JSON_THROW_ON_ERROR)]);

                    return $record;
                }, 3);
            } catch (UniqueConstraintViolationException $error) {
                if ($attempt === 2) {
                    throw $error;
                }
            }
        }
        throw new \LogicException('Action confirmation retry exhausted.');
    }
}
