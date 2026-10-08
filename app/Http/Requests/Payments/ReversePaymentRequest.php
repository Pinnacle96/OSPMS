<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

class ReversePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reverse', $this->route('payment'));
    }

    public function rules(): array
    {
        return ['reason' => 'required|string|max:2000', 'idempotency_key' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/D']];
    }
}
