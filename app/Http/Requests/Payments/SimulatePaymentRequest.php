<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

class SimulatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pay', $this->route('ticket') ?? $this->route('payment')->ticket);
    }

    public function rules(): array
    {
        return ['scenario' => 'required|in:successful,failed,pending', 'idempotency_key' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/D']];
    }
}
