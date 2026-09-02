<?php

namespace App\Http\Requests;

use App\Enums\MediaKind;
use App\Support\UploadedDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncidentAttachmentRequest extends FormRequest
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
            'file' => UploadedDocument::rules(10240, UploadedDocument::INCIDENT_MIME_TYPES),
            // Only when the client wants a label other than the uploaded file's own.
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'caption' => ['sometimes', 'nullable', 'string', 'max:255'],
            'media_kind' => ['sometimes', Rule::enum(MediaKind::class)],
        ];
    }
}
