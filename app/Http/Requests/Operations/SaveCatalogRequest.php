<?php

namespace App\Http\Requests\Operations;

use App\Domains\System\Services\CatalogDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $d = app(CatalogDefinition::class);
        $kind = $d->kind($this);
        $id = $this->route('record');

        return Gate::allows($id ? 'update' : 'create', $id ? $d->record($kind, $id) : $d->model($kind));
    }

    public function rules(): array
    {
        $kind = $this->route('catalog');
        $rules = [];
        $optional = fn ($max) => ['nullable', 'string', 'max:'.$max];
        if ($kind === 'operators') {
            $rules = [
                'name' => 'required|string|max:190', 'registration_number' => $optional(100), 'contact_person' => $optional(150), 'phone' => $optional(30), 'email' => 'nullable|email|max:190', 'address' => $optional(5000),
                'status' => ['required', Rule::in(['pending', 'approved', 'suspended', 'inactive'])],
                'park_ids' => 'required|array|min:1', 'park_ids.*' => 'required|integer|distinct|exists:parks,id', 'route_keys' => 'present|array', 'route_keys.*' => ['string', 'distinct', 'regex:/^[1-9][0-9]*:[1-9][0-9]*$/D'],
            ];
        }
        if ($kind === 'drivers') {
            $rules = [
                'first_name' => 'required|string|max:100', 'middle_name' => $optional(100), 'last_name' => 'required|string|max:100', 'phone' => 'required|string|max:30', 'email' => 'nullable|email|max:190', 'residential_address' => $optional(5000), 'licence_number' => $optional(100), 'licence_expiry' => 'nullable|date_format:Y-m-d', 'emergency_contact_name' => $optional(150), 'emergency_contact_phone' => $optional(30), 'next_of_kin' => $optional(150),
                'status' => ['required', Rule::in(['pending', 'active', 'suspended', 'expired', 'blacklisted', 'inactive'])],
            ];
        }
        if ($kind === 'vehicles') {
            $r = $this->route('record') ? app(CatalogDefinition::class)->record($kind, $this->route('record')) : null;
            $rules = ['registration_number' => ['required', 'string', 'max:30', Rule::unique('vehicles')->ignore($r)], 'vehicle_type' => ['required', Rule::in(['bus', 'minibus', 'taxi', 'tricycle', 'motorcycle', 'other'])],
                'make' => $optional(100), 'model' => $optional(100), 'colour' => $optional(50), 'manufacture_year' => 'nullable|integer|min:1900|max:'.(now()->year + 1), 'owner_name' => $optional(190), 'owner_phone' => $optional(30), 'roadworthiness_expiry' => 'nullable|date_format:Y-m-d', 'insurance_expiry' => 'nullable|date_format:Y-m-d', 'status' => ['required', Rule::in(['pending', 'active', 'suspended', 'expired', 'inactive'])]];
        }
        if ($kind === 'revenue-heads') {
            $r = $this->route('record') ? app(CatalogDefinition::class)->record($kind, $this->route('record')) : null;
            $rules = ['code' => ['required', 'string', 'max:50', Rule::unique('revenue_heads')->ignore($r)], 'name' => 'required|string|max:190', 'description' => $optional(5000), 'frequency' => ['required', Rule::in(['daily', 'per_entry', 'per_trip', 'one_time', 'periodic', 'other'])], 'status' => ['required', Rule::in(['active', 'inactive'])]];
        }
        if ($kind === 'fee-configurations') {
            $rules = [
                'revenue_head_id' => 'required|integer|exists:revenue_heads,id', 'amount' => ['required', 'regex:/^(0|[1-9][0-9]{0,12})(?:\.[0-9]{1,2})?$/D'], 'currency' => 'required|in:NGN',
                'vehicle_type' => ['nullable', Rule::in(['bus', 'minibus', 'taxi', 'tricycle', 'motorcycle', 'other'])], 'lga_id' => 'nullable|integer|exists:lgas,id', 'park_id' => 'nullable|integer|exists:parks,id', 'route_id' => 'nullable|integer|exists:routes,id', 'priority' => 'required|integer|min:-10000|max:10000',
                'effective_from' => 'required|date', 'effective_to' => 'nullable|date|after:effective_from', 'status' => ['required', Rule::in(['draft', 'active', 'expired', 'inactive'])],
            ];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('catalog') === 'vehicles') {
            $this->merge(['registration_number' => mb_strtoupper(trim((string) $this->input('registration_number')))]);
        }
    }
}
