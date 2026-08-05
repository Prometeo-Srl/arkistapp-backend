<?php

namespace App\Http\Requests;

use App\Enums\CompanySizeBand;
use App\Models\OrgRole;
use App\Support\OrgChartEntryRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The whole org chart, submitted once by "conferma e concludi". Nothing is
 * written before that: abandoning the flow at "crea in seguito" must not leave
 * accounts behind for people who were only half entered.
 */
class StoreOrgChartRequest extends FormRequest
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
            // Step 1. Optional: the prototype lets you skip straight past it.
            'size_band' => ['nullable', Rule::enum(CompanySizeBand::class)],

            'roles' => ['present', 'array'],
            'roles.*.code' => ['required', 'string', Rule::exists('org_roles', 'code')],
            ...OrgChartEntryRules::rules('roles.*.entries'),
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $roles = $this->validated()['roles'] ?? [];

                $codes = array_column($roles, 'code');
                if (count($codes) !== count(array_unique($codes))) {
                    $validator->errors()->add('roles', 'The same role appears more than once.');
                }

                $byCode = OrgRole::query()->whereIn('code', $codes)->get()->keyBy('code');

                foreach ($roles as $index => $role) {
                    OrgChartEntryRules::check(
                        $validator,
                        "roles.{$index}.entries",
                        $byCode->get($role['code']),
                        $role['entries'] ?? [],
                    );
                }
            },
        ];
    }
}
