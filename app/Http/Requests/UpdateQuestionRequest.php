<?php

namespace App\Http\Requests;

use App\Enums\QuestionType;
use App\Support\ChecklistRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Same shape as creation; providing `options` replaces the question's options wholesale. */
class UpdateQuestionRequest extends FormRequest
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
            ...ChecklistRules::question(keyed: false),
            'label' => ['sometimes', 'required', 'string', 'max:2000'],
            'type' => ['sometimes', Rule::enum(QuestionType::class)],
        ];
    }
}
