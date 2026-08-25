<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Reads the sharing list of one category/folder/file. */
class IndexAccessGrantRequest extends FormRequest
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
            'grantable_type' => ['required', Rule::in(['category', 'folder', 'file'])],
            'grantable_id' => ['required', 'integer'],
        ];
    }
}
