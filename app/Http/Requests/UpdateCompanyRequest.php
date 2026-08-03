<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:20'],
            'tax_code' => ['nullable', 'string', 'max:20'],
            'legal_address' => ['nullable', 'string', 'max:255'],
            'ateco_code' => ['nullable', 'string', 'max:20'],
            'employees_count' => ['nullable', 'integer', 'min:1'],
            'logo_path' => ['nullable', 'string', 'max:255'],
        ];
    }
}
