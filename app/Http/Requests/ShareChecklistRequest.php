<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Condivisione: the named people who must answer, and when it is due. Publishing
 * and assigning are one act (ADR-0005), so the recipient list cannot be empty -
 * a published checklist nobody can answer is not a reachable state.
 */
class ShareChecklistRequest extends FormRequest
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
            'assignee_user_ids' => ['required', 'array', 'min:1'],
            'assignee_user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
        ];
    }
}
