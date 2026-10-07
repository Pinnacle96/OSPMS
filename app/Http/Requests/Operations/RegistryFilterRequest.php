<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistryFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $kind = $this->route()->defaults['registry'] ?? 'parks';

        return ['search' => ['nullable', 'string', 'max:190'], 'status' => ['nullable', Rule::in($kind === 'parks' ? ['pending', 'active', 'suspended', 'inactive'] : ['active', 'inactive'])],
            'lga_id' => ['nullable', 'integer'], 'sort' => ['nullable', Rule::in($kind === 'routes' ? ['route_code', 'origin', 'destination', 'status', 'created_at'] : ['name', 'status', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])], 'page' => ['nullable', 'integer', 'min:1']];
    }
}
