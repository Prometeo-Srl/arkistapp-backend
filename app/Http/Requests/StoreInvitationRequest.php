<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvitationRequest extends FormRequest
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
            'email' => [
                'required', 'email',
                // Mirrors the partial unique index: one pending invitation per (company, email).
                Rule::unique('invitations', 'email')->where('company_id', $company->getKey()),
            ],
            'org_role_id' => ['nullable', 'integer', Rule::exists('org_roles', 'id')],
            'is_admin' => ['sometimes', 'boolean'],
        ];
    }
}
