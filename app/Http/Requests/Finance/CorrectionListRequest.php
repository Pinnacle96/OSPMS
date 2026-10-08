<?php

namespace App\Http\Requests\Finance;

use App\Domains\Finance\Models\FinancialAdjustment;
use App\Domains\Finance\Models\Refund;
use Illuminate\Foundation\Http\FormRequest;

class CorrectionListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', $this->routeIs('finance.refunds.*') ? Refund::class : FinancialAdjustment::class);
    }

    public function rules(): array
    {
        $refund = $this->routeIs('finance.refunds.*');

        return ['search' => 'nullable|string|max:190', 'status' => $refund ? 'nullable|in:requested,approved,rejected,processing,successful,failed' : 'nullable|in:requested,approved,rejected', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d'.($this->filled('from') ? '|after_or_equal:from' : ''), 'sort' => 'nullable|in:requested_at,amount,'.($refund ? 'refund_reference' : 'adjustment_reference'), 'order' => 'nullable|in:asc,desc'];
    }
}
