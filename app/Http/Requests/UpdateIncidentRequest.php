<?php

namespace App\Http\Requests;

use App\Enums\IncidentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One request, two roles: reviewers (admin/operator) drive the review/closure
 * fields, the reporter edits their own draft's factual fields.
 */
class UpdateIncidentRequest extends FormRequest
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
        $incident = $this->route('incident');
        $canReview = $this->user()->isOperator() || $this->user()->isAdminOf($incident->company);

        if ($canReview) {
            return [
                // `submitted` is allowed back: the detail (290) has
                // "contrassegna da leggere" alongside "ho preso visione",
                // which hands a report back to the unread pile.
                'status' => ['sometimes', Rule::in([IncidentStatus::Submitted->value, IncidentStatus::UnderReview->value, IncidentStatus::Closed->value])],
                'causes' => ['sometimes', 'nullable', 'string'],
                'actions_taken' => ['sometimes', 'nullable', 'string'],
                'inail_ref' => ['sometimes', 'nullable', 'string', 'max:255'],
            ];
        }

        return [
            'description' => ['sometimes', 'string'],
            'causes' => ['sometimes', 'nullable', 'string'],
            'actions_taken' => ['sometimes', 'nullable', 'string'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'department' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
