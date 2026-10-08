<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match ($this->route()->getName()) {
            'dashboard.state' => $this->user()->can('view_state_dashboard'),
            'dashboard.executive' => $this->user()->can('view_executive_dashboard'),
            'dashboard.revenue' => $this->user()->can('view_revenue_dashboard'),
            default => $this->user()->can($this->routeIs('dashboard.lga') ? 'view_lga' : 'view_park'),
        };
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d'],
            'lga_id' => ['nullable', 'integer', 'min:1'], 'park_id' => ['nullable', 'integer', 'min:1'],
            'revenue_head_id' => ['nullable', 'integer', 'min:1'],
            'channel' => ['nullable', 'in:cashless_pos,transfer,ussd,gateway,demo,other'],
        ];
    }
}
