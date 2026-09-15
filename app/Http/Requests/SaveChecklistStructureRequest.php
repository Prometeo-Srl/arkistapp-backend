<?php

namespace App\Http\Requests;

use App\Support\ChecklistRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The whole questionnaire as one payload. It is authoritative - anything it
 * omits is deleted - so an empty `sections` array is a legitimate "the DDL
 * emptied the checklist", not a missing field.
 */
class SaveChecklistStructureRequest extends FormRequest
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
            'sections' => ['present', 'array'],
            'sections.*.uuid' => ['required', 'uuid', 'distinct'],
            'sections.*.title' => ['required', 'string', 'max:2000'],
            'sections.*.position' => ['required', 'integer', 'min:0'],
            'sections.*.questions' => ['nullable', 'array'],
            ...ChecklistRules::question('sections.*.questions.*.'),
        ];
    }
}
