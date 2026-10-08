<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

class CatalogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['search' => 'nullable|string|max:190', 'status' => 'nullable|string|max:30', 'vehicle_type' => 'nullable|in:bus,minibus,taxi,tricycle,motorcycle,other', 'park_id' => 'nullable|integer', 'operator_id' => 'nullable|integer', 'lga_id' => 'nullable|integer', 'revenue_head_id' => 'nullable|integer', 'sort' => 'nullable|in:created_at,name,status,registration_number,first_name,amount,effective_from,starts_at', 'direction' => 'nullable|in:asc,desc'];
    }
}
