<?php

namespace App\Http\Requests;

use App\Enums\MembershipStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
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
        return [
            'is_admin' => ['sometimes', 'boolean'],
            'department' => ['nullable', 'string', 'max:255'],
            'employee_code' => ['nullable', 'string', 'max:255'],
            'hired_at' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::enum(MembershipStatus::class)],
            'org_role_ids' => ['nullable', 'array'],
            'org_role_ids.*' => ['integer', Rule::exists('org_roles', 'id')],
        ];
    }
}
