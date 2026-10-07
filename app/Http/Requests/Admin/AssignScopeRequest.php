<?php

namespace App\Http\Requests\Admin;

use App\Domains\Identity\Enums\AccessLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignScopeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignScopes', $this->route('user'));
    }

    public function rules(): array
    {
        $rules = [];
        foreach (['lgas', 'parks', 'operators'] as $table) {
            $rules[$table] = ['present', 'array'];
            $rules[$table.'.*.id'] = ['required', 'integer', 'distinct', Rule::exists($table, 'id')->whereNull('deleted_at')];
            $rules[$table.'.*.access_level'] = ['required', Rule::enum(AccessLevel::class)];
        }

        return $rules;
    }
}
