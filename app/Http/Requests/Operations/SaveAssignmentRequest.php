<?php

namespace App\Http\Requests\Operations;

use App\Domains\Assignments\Models\DriverAssignment;
use Illuminate\Foundation\Http\FormRequest;

class SaveAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', DriverAssignment::class);
    }

    public function rules(): array
    {
        return ['driver_id' => 'required|integer|exists:drivers,id', 'vehicle_id' => 'required|integer|exists:vehicles,id', 'operator_id' => 'required|integer|exists:operators,id', 'park_id' => 'required|integer|exists:parks,id', 'route_id' => 'nullable|integer|exists:routes,id', 'starts_at' => 'required|date|before_or_equal:now', 'is_primary' => 'required|boolean'];
    }
}
