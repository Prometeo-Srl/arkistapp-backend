<?php

namespace App\Http\Requests;

use App\Enums\SupportMessageKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupportMessageRequest extends FormRequest
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
            'body' => ['required', 'string'],
            'kind' => ['sometimes', Rule::in([SupportMessageKind::Text->value, SupportMessageKind::CallLog->value])],
            'call_duration_seconds' => ['required_if:kind,call_log', 'nullable', 'integer', 'min:0'],
        ];
    }
}
