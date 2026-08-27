<?php

namespace App\Http\Requests;

use App\Enums\FileVisibility;
use App\Enums\MediaKind;
use App\Support\UploadedDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFileRequest extends FormRequest
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
            'file' => UploadedDocument::rules(),
            'name' => ['nullable', 'string', 'max:255'],
            'folder_id' => ['required', 'integer', Rule::exists('folders', 'id')],
            'document_type_id' => ['nullable', 'integer', Rule::exists('document_types', 'id')],
            'issued_at' => ['nullable', 'date'],
            'requires_acknowledgement' => ['nullable', 'boolean'],
            'requires_signature' => ['nullable', 'boolean'],
            'media_kind' => ['nullable', Rule::enum(MediaKind::class)],
            // "configura file" (prototype 235–236) runs before the upload, so the
            // two fields that used to arrive by PATCH may come with the bytes.
            // Left out, expires_at is derived from the document type as usual.
            'expires_at' => ['nullable', 'date'],
            'visibility' => ['nullable', Rule::enum(FileVisibility::class)],
        ];
    }
}
