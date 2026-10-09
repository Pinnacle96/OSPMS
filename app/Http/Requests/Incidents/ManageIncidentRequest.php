<?php

namespace App\Http\Requests\Incidents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManageIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage_incident');
    }

    public static function inputRules(): array
    {
        return ['expected_status' => ['required', Rule::in(['reported', 'under_review', 'escalated', 'resolved'])], 'status' => ['required', Rule::in(['under_review', 'escalated', 'resolved', 'closed'])], 'resolution' => 'required|string|min:10|max:4000', 'idempotency_key' => 'required|string|regex:/\A[a-f0-9]{64}\z/D'];
    }

    public function rules(): array
    {
        return self::inputRules();
    }
}
