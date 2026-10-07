<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        foreach (['email', 'username'] as $field) {
            if ($this->filled($field)) {
                $this->merge([$field => mb_strtolower(trim($this->input($field)))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'], 'email' => ['nullable', 'required_without:username', 'email', 'max:190', Rule::unique('users')->ignore($this->user()->id)],
            'username' => ['nullable', 'required_without:email', 'string', 'max:80', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users')->ignore($this->user()->id)], 'phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
