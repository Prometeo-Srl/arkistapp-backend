<?php

namespace App\Http\Requests;

use App\Support\UploadedDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An image the author pins to a question or an option. It is a stored path, not
 * a `File` row: the document infrastructure carries folders, versions and access
 * grants a builder image has no use for.
 */
class StoreChecklistImageRequest extends FormRequest
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
            'image' => UploadedDocument::rules(
                UploadedDocument::IMAGE_MAX_KILOBYTES,
                UploadedDocument::IMAGE_MIME_TYPES,
            ),
        ];
    }
}
