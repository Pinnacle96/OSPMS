<?php

namespace App\Http\Requests\Finance;

use App\Domains\Finance\Models\Settlement;
use Illuminate\Foundation\Http\FormRequest;

class CreateSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Settlement::class) ?? false;
    }

    public function rules(): array
    {
        return ['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from', 'transaction_ids' => 'required|array|min:1|max:500', 'transaction_ids.*' => 'required|integer|distinct', 'scenario' => 'required|in:matched,amount_mismatch', 'idempotency_key' => 'required|string|size:64'];
    }
}
