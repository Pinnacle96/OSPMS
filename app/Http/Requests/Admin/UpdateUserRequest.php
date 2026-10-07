<?php

namespace App\Http\Requests\Admin;

class UpdateUserRequest extends StoreUserRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }
}
