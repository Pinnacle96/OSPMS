<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class ReviewCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->routeIs('finance.refunds.process') ? 'process' : 'approve', $this->route('refund') ?? $this->route('adjustment'));
    }

    public function rules(): array
    {
        return ['reason' => 'required|string|min:10|max:2000', 'idempotency_key' => 'required|string|size:64', ...($this->routeIs('finance.refunds.process') ? ['scenario' => 'required|in:successful,failed,processing'] : ['decision' => 'required|in:approved,rejected'])];
    }
}
