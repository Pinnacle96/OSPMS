<?php

namespace App\Http\Requests\Finance;

use App\Domains\Finance\Models\Settlement;
use Illuminate\Foundation\Http\FormRequest;

class FinanceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->routeIs('finance.settlements.*') ? $this->user()->can('viewAny', Settlement::class) : $this->user()->can('view_reconciliation');
    }

    public function rules(): array
    {
        $rules = ['search' => 'nullable|string|max:190', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d', 'order' => 'nullable|in:asc,desc'];
        if ($this->filled('from') && $this->filled('to')) {
            $rules['to'] .= '|after_or_equal:from';
        }
        if ($this->routeIs('finance.settlements.*')) {
            return $rules + ['status' => 'nullable|in:pending,processing,settled,exception,reversed', 'sort' => 'nullable|in:settlement_reference,gross_amount,net_amount,settled_at,created_at'];
        }
        if ($this->routeIs('finance.reconciliation.index')) {
            return $rules + ['status' => 'nullable|in:queued,running,completed,completed_with_exceptions,failed', 'sort' => 'nullable|in:started_at,period_start,reconciliation_reference'];
        }

        return $rules + ['status' => 'nullable|in:unreconciled,matched,exception,under_review,reconciled', 'exception_type' => 'nullable|in:payment_without_ticket,ticket_without_payment,duplicate_provider_reference,amount_mismatch,missing_ledger_entry,missing_settlement,reversal_exception,unknown', 'lga_id' => 'nullable|integer', 'park_id' => 'nullable|integer', 'sort' => 'nullable|in:created_at,expected_amount,actual_amount,difference_amount'];
    }
}
