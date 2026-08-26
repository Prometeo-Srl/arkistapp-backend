<?php

namespace App\Http\Requests;

use App\Enums\FileVisibility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Metadata only. expires_at may be set by hand; left out, the model
 * derives it from issued_at + the document type validity.
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
            // "posizione" (prototype 204): the move. The target folder has to be
            // in the same workspace, which the controller checks — the tenant is
            // three hops up and out of reach of a plain exists rule.
            'folder_id' => ['sometimes', 'required', 'integer', Rule::exists('folders', 'id')],
            'document_type_id' => ['nullable', 'integer', Rule::exists('document_types', 'id')],
            'issued_at' => ['nullable', 'date'],
            // Set by hand from "gestisci configurazione" (prototype 236); null clears it.
            // The model only derives an expiry when this field is left alone.
            'expires_at' => ['nullable', 'date'],
            'requires_acknowledgement' => ['nullable', 'boolean'],
            'requires_signature' => ['nullable', 'boolean'],
            // "gestisci accesso" (prototype 204): whether the folder's grants reach
            // the document, or only the ones handed out on it.
            'visibility' => ['sometimes', 'required', Rule::enum(FileVisibility::class)],
        ];
    }
}
