<?php

namespace App\Http\Requests\Complaints;

use Illuminate\Foundation\Http\FormRequest;

class ManageComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('complaint'));
    }

    public static function inputRules(): array
    {
        return ['idempotency_key' => 'required|string|regex:/\A[a-f0-9]{64}\z/D', 'action' => 'required|in:status,assign,note', 'expected_status' => 'required|in:submitted,received,assigned,under_review,resolved,closed', 'expected_assignee' => 'nullable|ulid', 'status' => 'required_if:action,status|in:received,under_review,resolved,closed', 'assignee' => 'required_if:action,assign|ulid', 'reason' => 'required_unless:action,note|nullable|string|min:10|max:4000', 'resolution' => 'required_if:status,resolved|nullable|string|min:10|max:4000', 'note' => 'required_if:action,note|nullable|string|min:2|max:4000', 'is_internal' => 'sometimes|boolean'];
    }

    public function rules(): array
    {
        return self::inputRules();
    }
}
