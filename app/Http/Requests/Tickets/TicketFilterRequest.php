<?php

namespace App\Http\Requests\Tickets;

use App\Domains\Ticketing\Enums\TicketPaymentStatus;
use App\Domains\Ticketing\Enums\TicketStatus;
use App\Domains\Ticketing\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TicketFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Ticket::class);
    }

    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:190', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'ticket_status' => ['nullable', Rule::enum(TicketStatus::class)], 'payment_status' => ['nullable', Rule::enum(TicketPaymentStatus::class)],
            'lga_id' => 'nullable|integer|min:1', 'park_id' => 'nullable|integer|min:1', 'revenue_head_id' => 'nullable|integer|min:1',
            'operator_id' => 'nullable|integer|min:1', 'driver_id' => 'nullable|integer|min:1', 'vehicle_id' => 'nullable|integer|min:1',
            'sort' => 'nullable|in:ticket_reference,issued_at,amount', 'direction' => 'nullable|in:asc,desc',
        ];
    }
}
