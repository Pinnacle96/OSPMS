<?php

namespace App\Http\Requests\Complaints;

use App\Domains\Complaints\Models\Complaint;
use Illuminate\Foundation\Http\FormRequest;

class CreateComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->routeIs('public.complaints.store') || $this->user()?->can('create', Complaint::class);
    }

    public static function inputRules(): array
    {
        return ['complainant_name' => 'required|string|min:2|max:190', 'complainant_phone' => ['nullable', 'string', 'max:30', 'regex:/\A[+0-9 ()\-]+\z/D'], 'complainant_email' => 'nullable|email:rfc|max:190', 'category' => 'required|string|min:2|max:80', 'description' => 'required|string|min:10|max:4000', 'park' => 'nullable|ulid', 'context' => 'nullable|string|max:107', 'file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|extensions:jpg,jpeg,png,pdf|max:5120', 'idempotency_key' => 'required|string|regex:/\A[a-f0-9]{64}\z/D'];
    }

    public function rules(): array
    {
        $r = self::inputRules();
        if ($this->routeIs('public.complaints.store')) {
            $r['context'] = 'prohibited';
        }

return $r;
    }
}
