<?php

namespace App\Http\Requests\Operations;

use App\Domains\Parks\Enums\ParkStatus;
use App\Domains\Parks\Models\Park;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveParkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('park') ? $this->user()->can('update', $this->route('park')) : $this->user()->can('create', Park::class);
    }

    public function rules(): array
    {
        return ['park_code' => ['required', 'string', 'max:50', Rule::unique('parks')->ignore($this->route('park'))],
            'lga_id' => ['required', 'integer', Rule::exists('lgas', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:190'], 'address' => ['required', 'string', 'max:3000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'decimal:0,7'], 'longitude' => ['nullable', 'numeric', 'between:-180,180', 'decimal:0,7'],
            'category' => ['nullable', 'string', 'max:50'], 'contact_phone' => ['nullable', 'string', 'max:30'], 'status' => ['required', Rule::enum(ParkStatus::class)]];
    }
}
