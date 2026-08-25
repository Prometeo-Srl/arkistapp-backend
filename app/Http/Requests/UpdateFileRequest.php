<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Metadata only: expires_at is never accepted from the API, the model
 * recalculates it from issued_at + the document type validity.
 */
class UpdateFileRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'document_type_id' => ['nullable', 'integer', Rule::exists('document_types', 'id')],
            'issued_at' => ['nullable', 'date'],
            'requires_acknowledgement' => ['nullable', 'boolean'],
            'requires_signature' => ['nullable', 'boolean'],
        ];
    }
}
