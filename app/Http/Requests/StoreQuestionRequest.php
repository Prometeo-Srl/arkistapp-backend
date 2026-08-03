<?php

namespace App\Http\Requests;

use App\Enums\QuestionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuestionRequest extends FormRequest
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
            'label' => ['required', 'string', 'max:255'],
            'help_text' => ['nullable', 'string'],
            'type' => ['required', Rule::enum(QuestionType::class)],
            'is_required' => ['sometimes', 'boolean'],
            'allows_attachment' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'options' => ['nullable', 'array'],
            'options.*.label' => ['required', 'string', 'max:255'],
            'options.*.image_path' => ['nullable', 'string', 'max:255'],
            'options.*.position' => ['sometimes', 'integer', 'min:0'],
            'options.*.is_non_conformity' => ['sometimes', 'boolean'],
        ];
    }
}
