<?php

namespace App\Http\Requests\Tickets;

use App\Domains\Ticketing\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;

class IssueTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Ticket::class);
    }

    public function rules(): array
    {
        return ['assignment_id' => 'required|integer|min:1', 'revenue_head_id' => 'required|integer|min:1', 'confirmation' => 'required|string|size:64', 'request_key' => ['required', 'string', 'regex:/\A[a-f0-9]{32}\z/D']];
    }
}
