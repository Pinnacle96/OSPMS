<?php

namespace App\Http\Requests\Incidents;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateIncidentRequest extends FormRequest
{
    public const CATEGORIES = ['dispute', 'unauthorized_collection', 'safety_issue', 'traffic_obstruction', 'violence', 'vehicle_incident', 'ticket_dispute', 'other'];

    public function authorize(): bool
    {
        return $this->user()->can('create_incident');
    }

    public static function inputRules(): array
    {
        return ['park' => 'required|ulid', 'context' => 'nullable|string|size:107', 'category' => ['required', Rule::in(self::CATEGORIES)], 'description' => 'required|string|min:10|max:4000', 'occurred_at' => ['bail', 'required', 'date_format:Y-m-d\TH:i', function ($attribute, $value, $fail) {
            if (CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $value, 'Africa/Lagos')->isFuture()) {
                $fail('The occurrence time must not be in the future (Africa/Lagos).');
            }
        }], 'file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|extensions:jpg,jpeg,png,pdf|max:5120', 'idempotency_key' => 'required|string|regex:/\A[a-f0-9]{64}\z/D'];
    }

    public function rules(): array
    {
        return self::inputRules();
    }
}
