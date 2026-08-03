<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Direct attachment of an existing user; email-first onboarding goes through invitations. */
class StoreMemberRequest extends FormRequest
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
        $company = $this->route('company');

        return [
            'user_id' => [
                'required', 'integer',
                Rule::exists('users', 'id'),
                Rule::unique('company_memberships', 'user_id')->where('company_id', $company->getKey()),
            ],
            'is_admin' => ['sometimes', 'boolean'],
            'department' => ['nullable', 'string', 'max:255'],
            'employee_code' => ['nullable', 'string', 'max:255'],
            'hired_at' => ['nullable', 'date'],
            'org_role_ids' => ['nullable', 'array'],
            'org_role_ids.*' => ['integer', Rule::exists('org_roles', 'id')],
        ];
    }
}
