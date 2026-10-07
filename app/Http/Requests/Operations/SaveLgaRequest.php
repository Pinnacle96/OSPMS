<?php

namespace App\Http\Requests\Operations;

use App\Domains\Geography\Enums\LgaStatus;
use App\Domains\Geography\Models\Lga;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveLgaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('lga') ? $this->user()->can('update', $this->route('lga')) : $this->user()->can('create', Lga::class);
    }

    public function rules(): array
    {
        return ['code' => ['required', 'string', 'max:30', Rule::unique('lgas')->ignore($this->route('lga'))],
            'name' => ['required', 'string', 'max:150', Rule::unique('lgas')->ignore($this->route('lga'))],
            'administrative_contact_name' => ['nullable', 'string', 'max:150'], 'administrative_contact_phone' => ['nullable', 'string', 'max:30'],
            'administrative_contact_email' => ['nullable', 'email', 'max:190'], 'status' => ['required', Rule::enum(LgaStatus::class)]];
    }
}
