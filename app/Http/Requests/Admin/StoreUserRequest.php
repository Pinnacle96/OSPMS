<?php

namespace App\Http\Requests\Admin;

use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
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
        $id = $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'required_without:username', 'email', 'max:190', Rule::unique('users')->ignore($id)],
            'username' => ['nullable', 'required_without:email', 'string', 'max:80', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users')->ignore($id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'password' => [$id ? 'nullable' : 'required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
            'must_change_password' => ['required', 'boolean'],
            'roles' => [$this->user()->can('manage_roles') ? 'sometimes' : 'prohibited', 'array'],
            'roles.*' => ['string', 'distinct', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ];
    }
}
