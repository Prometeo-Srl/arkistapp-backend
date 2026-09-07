<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** One row of "Gestisci autorizzazioni": both switches of a single role. */
class UpdateOrgRolePermissionRequest extends FormRequest
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
            'can_view_org_chart' => ['required', 'boolean'],
            'can_view_incidents' => ['required', 'boolean'],
        ];
    }
}
