<?php

namespace App\Http\Requests;

use App\Support\OrgChartEntryRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * One role's entries, for editing the org chart after it has been set up. The
 * initial "Imposta Organigramma" run submits everything at once instead; see
 * StoreOrgChartRequest.
 */
class UpdateOrgChartRoleRequest extends FormRequest
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
        return OrgChartEntryRules::rules('entries');
    }

    public function after(): array
    {
        return [
            fn (Validator $validator) => OrgChartEntryRules::check(
                $validator,
                'entries',
                $this->route('orgRole'),
                $this->validated()['entries'] ?? [],
            ),
        ];
    }
}
