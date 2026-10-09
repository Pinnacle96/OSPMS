<?php

namespace App\Http\Requests\Enforcement;

use App\Domains\Enforcement\Models\Inspection;
use Illuminate\Foundation\Http\FormRequest;

class RecordInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Inspection::class);
    }

    public function rules(): array
    {
        return self::inspectionRules();
    }

    public static function inspectionRules(): array
    {
        return ['ticket' => 'nullable|string|size:26', 'context' => 'required_without:ticket|nullable|string|size:107', 'inspection_type' => 'required|in:ticket_verification,driver_check,vehicle_check,operator_check', 'result' => 'required|in:compliant,non_compliant,requires_review', 'notes' => 'nullable|string|max:4000', 'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90', 'regex:/\A-?[0-9]{1,3}(?:\.[0-9]{1,7})?\z/D'], 'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180', 'regex:/\A-?[0-9]{1,3}(?:\.[0-9]{1,7})?\z/D'], 'idempotency_key' => 'required|string|size:64'];
    }
}
