<?php

namespace App\Http\Requests\Tickets;

use Illuminate\Foundation\Http\FormRequest;

class CancelTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cancel', $this->route('ticket'));
    }

    public function rules(): array
    {
        return ['reason' => 'required|string|max:2000'];
    }
}
