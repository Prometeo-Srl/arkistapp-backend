<?php

namespace App\Http\Requests;

use App\Enums\GranteeType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $targetsUsers = $this->input('assignee_type') === GranteeType::User->value;

        return [
            'assignee_type' => ['required', Rule::enum(GranteeType::class)],
            'assignee_id' => ['required', 'integer', Rule::exists($targetsUsers ? 'users' : 'org_roles', 'id')],
            'due_at' => ['nullable', 'date'],
        ];
    }
}
