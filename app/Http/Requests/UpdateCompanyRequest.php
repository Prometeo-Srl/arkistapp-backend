<?php

namespace App\Http\Requests;

use App\Enums\CompanySizeBand;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // "modifica dati personali" (prototype 080) edits the p.iva, so the
            // same 11 digits and the same uniqueness registration demands apply
            // here too — without the rule the partial unique index on
            // (vat_number) where kind = 'business' answers with a 500 instead
            // of a 422.
            'vat_number' => [
                'nullable', 'string', 'regex:/^\d{11}$/',
                Rule::unique('companies', 'vat_number')->ignore($this->route('company')),
            ],
            'tax_code' => ['nullable', 'string', 'max:20'],
            'legal_address' => ['nullable', 'string', 'max:255'],
            // The other three quarters of the indirizzo, collected separately by
            // "Registrazione" step 2 and editable on the same row of 080.
            'postal_code' => ['nullable', 'string', 'digits:5'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'size:2', 'alpha'],
            'ateco_code' => ['nullable', 'string', 'max:20'],
            'employees_count' => ['nullable', 'integer', 'min:1'],
            // "Imposta Organigramma" step 1.
            'size_band' => ['nullable', Rule::enum(CompanySizeBand::class)],
            'logo_path' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'vat_number.regex' => 'The VAT number must be 11 digits.',
        ];
    }

    /** Same normalisation RegisterCompanyRequest applies: "rm" and "RM" are one province. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->province)) {
            $this->merge(['province' => strtoupper($this->province)]);
        }
    }
}
