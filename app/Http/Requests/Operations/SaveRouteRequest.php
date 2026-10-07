<?php

namespace App\Http\Requests\Operations;

use App\Domains\Routes\Enums\RouteStatus;
use App\Domains\Routes\Models\Route;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('route') ? $this->user()->can('update', $this->route('route')) : $this->user()->can('create', Route::class);
    }

    public function rules(): array
    {
        return ['route_code' => ['required', 'string', 'max:50', Rule::unique('routes')->ignore($this->route('route'))],
            'origin' => ['required', 'string', 'max:190'], 'destination' => ['required', 'string', 'max:190', 'different:origin'],
            'description' => ['nullable', 'string', 'max:3000'], 'status' => ['required', Rule::enum(RouteStatus::class)]];
    }
}
