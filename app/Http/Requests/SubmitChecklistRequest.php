<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Structural shape of a submission. Whether the right questions are answered,
 * with values matching each question type, is enforced in the controller once
 * the checklist template is loaded.
 */
class SubmitChecklistRequest extends FormRequest
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
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.value_text' => ['nullable', 'string'],
            'answers.*.value_date' => ['nullable', 'date'],
            'answers.*.value_time' => ['nullable', 'date_format:H:i'],
            'answers.*.value_number' => ['nullable', 'numeric'],
            'answers.*.selected_option_ids' => ['nullable', 'array'],
            'answers.*.selected_option_ids.*' => ['integer'],
            'answers.*.attachment_path' => ['nullable', 'string', 'max:255'],
        ];
    }
}
