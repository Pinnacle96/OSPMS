<?php

namespace App\Http\Requests\Finance;

use App\Domains\Finance\Models\FinancialAdjustment;
use Illuminate\Foundation\Http\FormRequest;

class RequestAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', FinancialAdjustment::class);
    }

    public function rules(): array
    {
        return ['original_transaction' => 'required|string|size:26', 'adjustment_type' => 'required|in:credit,debit', 'amount' => 'required|string|max:16', 'reason' => 'required|string|min:10|max:2000', 'idempotency_key' => 'required|string|size:64'];
    }
}
