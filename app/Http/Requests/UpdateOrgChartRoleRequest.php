<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * One "inserisci <ruolo>" screen's worth of entries, saved by "salva e continua".
 * The list is authoritative: whoever is missing from it loses the appointment.
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
        return [
            'entries' => ['present', 'array'],
            'entries.*.name' => ['nullable', 'string', 'max:255'],
            // Absent when the entry is the signed-in user ("sono io"), who is
            // identified by their account rather than by what was typed.
            'entries.*.email' => ['nullable', 'email', 'max:255', 'required_without:entries.*.is_me'],
            'entries.*.is_me' => ['boolean'],
            // "rlst (rls territoriale)"; ignored for every role other than RLS.
            'entries.*.is_territorial' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $entries = $this->validated()['entries'] ?? [];

                if ($this->route('orgRole')?->is_unique_per_company && count($entries) > 1) {
                    $validator->errors()->add('entries', 'This role allows a single person per company.');
                }

                $mine = array_filter($entries, fn (array $e) => (bool) ($e['is_me'] ?? false));
                if (count($mine) > 1) {
                    $validator->errors()->add('entries', 'Only one entry can be marked as yourself.');
                }

                // Two rows for the same person would collapse into one appointment
                // anyway; rejecting it is clearer than silently deduplicating.
                $emails = array_map(
                    fn (array $e) => strtolower(trim((string) ($e['email'] ?? ''))),
                    array_filter($entries, fn (array $e) => ! ($e['is_me'] ?? false)),
                );
                if (count($emails) !== count(array_unique($emails))) {
                    $validator->errors()->add('entries', 'The same email appears more than once.');
                }
            },
        ];
    }
}
