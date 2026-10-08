<?php

namespace App\Http\Requests\Finance;

use App\Domains\Reconciliation\Models\ReconciliationRun;
use Illuminate\Foundation\Http\FormRequest;

class StartReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ReconciliationRun::class) ?? false;
    }

    public function rules(): array
    {
        return ['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from', 'lga_id' => 'nullable|integer|exists:lgas,id', 'park_id' => 'nullable|integer|exists:parks,id', 'provider' => 'nullable|in:demo', 'idempotency_key' => 'required|string|size:64'];
    }
}
