<?php

namespace App\Http\Requests;

use App\Enums\MediaKind;
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
            'file' => ['required', 'file', 'max:10240'],
            'caption' => ['sometimes', 'nullable', 'string', 'max:255'],
            'media_kind' => ['sometimes', Rule::enum(MediaKind::class)],
        ];
    }
}
