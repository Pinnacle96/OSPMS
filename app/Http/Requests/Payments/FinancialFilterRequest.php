<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

class FinancialFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->routeIs('finance.*') ? 'view_financial_ledger' : 'view_payment');
    }

    public function rules(): array
    {
        return ['search' => 'nullable|string|max:190', 'status' => 'nullable|in:pending,successful,failed,reversed,refunded', 'direction' => 'nullable|in:credit,debit', 'channel' => 'nullable|in:cashless_pos,transfer,ussd,gateway,demo,other', 'transaction_type' => 'nullable|in:payment,reversal,refund,adjustment', 'from' => 'nullable|date_format:Y-m-d', 'to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])], 'lga_id' => 'nullable|integer|min:1', 'park_id' => 'nullable|integer|min:1', 'revenue_head_id' => 'nullable|integer|min:1', 'operator_id' => 'nullable|integer|min:1', 'driver_id' => 'nullable|integer|min:1', 'vehicle_id' => 'nullable|integer|min:1', 'sort' => 'nullable|in:amount,initiated_at,occurred_at,payment_reference,transaction_reference', 'order' => 'nullable|in:asc,desc'];
    }
}
