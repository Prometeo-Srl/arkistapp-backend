<?php

namespace App\Support;

use App\Models\OrgRole;
use Illuminate\Validation\Validator;

/**
 * Shared by the two ways an org chart is written: the whole thing at once from
 * "conferma e concludi", and one role at a time when it is edited later. The
 * checks have to agree, so they live in one place.
 */
class OrgChartEntryRules
{
    /**
     * Field rules for a list of entries at `$prefix` (e.g. `entries` or
     * `roles.*.entries`).
     *
     * @return array<string, array<mixed>>
     */
    public static function rules(string $prefix): array
    {
        return [
            $prefix => ['present', 'array'],
            "{$prefix}.*.name" => ['nullable', 'string', 'max:255'],
            // Absent when the entry is the signed-in user ("sono io"), who is
            // identified by their account rather than by what was typed.
            "{$prefix}.*.email" => ['nullable', 'email', 'max:255', "required_without:{$prefix}.*.is_me"],
            "{$prefix}.*.is_me" => ['boolean'],
            // "rlst (rls territoriale)"; ignored for every role other than RLS.
            "{$prefix}.*.is_territorial" => ['boolean'],
        ];
    }

    /**
     * Cross-entry checks for one role's list.
     *
     * @param  array<int, array<string, mixed>>  $entries
     */
    public static function check(Validator $validator, string $key, ?OrgRole $role, array $entries): void
    {
        if ($role?->is_unique_per_company && count($entries) > 1) {
            $validator->errors()->add($key, 'This role allows a single person per company.');
        }

        $mine = array_filter($entries, fn (array $e) => (bool) ($e['is_me'] ?? false));
        if (count($mine) > 1) {
            $validator->errors()->add($key, 'Only one entry can be marked as yourself.');
        }

        // Two rows for the same person would collapse into one appointment anyway;
        // rejecting it is clearer than silently deduplicating.
        $emails = array_map(
            fn (array $e) => strtolower(trim((string) ($e['email'] ?? ''))),
            array_filter($entries, fn (array $e) => ! ($e['is_me'] ?? false)),
        );
        if (count($emails) !== count(array_unique($emails))) {
            $validator->errors()->add($key, 'The same email appears more than once.');
        }
    }
}
