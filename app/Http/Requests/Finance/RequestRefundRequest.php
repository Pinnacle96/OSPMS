<?php

namespace App\Http\Requests\Finance;

use App\Domains\Finance\Models\Refund;
use Illuminate\Foundation\Http\FormRequest;

class RequestRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('request', [Refund::class, $this->route('payment')]);
    }

    public function rules(): array
    {
        return ['amount' => 'required|string|max:16', 'reason' => 'required|string|min:10|max:2000', 'idempotency_key' => 'required|string|size:64'];
    }
}
