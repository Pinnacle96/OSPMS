<?php

namespace App\Http\Requests\Incidents;

use Illuminate\Foundation\Http\FormRequest;

class UploadEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('upload_evidence');
    }

    public static function inputRules(): array
    {
        return ['file' => 'required|file|mimes:jpg,jpeg,png,pdf|extensions:jpg,jpeg,png,pdf|max:5120', 'idempotency_key' => 'required|string|regex:/\A[a-f0-9]{64}\z/D'];
    }

    public function rules(): array
    {
        return self::inputRules();
    }
}
