<?php

namespace App\Http\Requests;

use App\Support\UploadedDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFileVersionRequest extends FormRequest
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
            'replaced_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
