<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignParkRoutesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignRoutes', $this->route('park'));
    }

    public function rules(): array
    {
        return ['route_ids' => ['present', 'array', 'max:200'], 'route_ids.*' => ['required', 'integer', 'distinct', Rule::exists('routes', 'id')->whereNull('deleted_at')->where('status', 'active')]];
    }
}
