<?php

namespace App\Http\Requests;

use App\Enums\IncidentKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncidentRequest extends FormRequest
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
            'kind' => ['required', Rule::enum(IncidentKind::class)],
            'is_anonymous' => ['sometimes', 'boolean'],
            'occurred_at' => ['sometimes', 'nullable', 'date'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'department' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['required', 'string'],
            // "note o osservazioni", the reporting wizard's last step: what the
            // reporter proposes should be done about it. The reviewer refines it
            // afterwards through UpdateIncidentRequest, which already takes it.
            'actions_taken' => ['sometimes', 'nullable', 'string'],
            'injured_person_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'absence_days' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
