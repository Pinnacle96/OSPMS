<?php

namespace App\Http\Requests\Admin;

use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', User::class);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:190'], 'status' => ['nullable', Rule::enum(UserStatus::class)],
            'role' => ['nullable', 'string', Rule::exists('roles', 'name')], 'sort' => ['nullable', Rule::in(['name', 'email', 'status', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])], 'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
