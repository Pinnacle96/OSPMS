<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class ResolveReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('item')) && $this->user()->can('resolve_reconciliation_exception') && $this->user()->can('access_statewide');
    }

    public function rules(): array
    {
        return ['note' => 'required|string|min:10|max:2000', 'status' => 'required|in:under_review,reconciled', 'idempotency_key' => 'required|string|size:64'];
    }
}
