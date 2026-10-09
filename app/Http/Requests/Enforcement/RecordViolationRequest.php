<?php

namespace App\Http\Requests\Enforcement;

use Illuminate\Foundation\Http\FormRequest;

class RecordViolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('record_violation');
    }

    public static function inputRules(): array
    {
        return ['category' => 'required|string|min:2|max:80', 'description' => 'required|string|min:10|max:4000', 'idempotency_key' => 'required|string|regex:/\A[a-f0-9]{64}\z/D'];
    }

    public function rules(): array
    {
        return self::inputRules();
    }
}
