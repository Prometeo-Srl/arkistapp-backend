<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * "Registrazione" for an employer: step 2 collects the company, step 3 the credentials.
 * No personal name is asked for anywhere in the flow — the profile is filled in later.
 */
class RegisterCompanyRequest extends FormRequest
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
            // Step 2 — "inserisci dati aziendali".
            'company_name' => ['required', 'string', 'max:255'],
            'vat_number' => ['required', 'string', 'regex:/^\d{11}$/', 'unique:companies,vat_number'],
            'legal_address' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'digits:5'],
            'city' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'size:2', 'alpha'],

            // Step 3 — "imposta credenziali di accesso". The helper text under both
            // password fields reads "minimo 8 caratteri, con una maiuscola e un numero".
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'regex:/\p{Lu}/u', Password::min(8)->numbers()],

            // Names the Sanctum token, same as LoginRequest.
            'device_name' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'vat_number.regex' => 'The VAT number must be 11 digits.',
            'password.regex' => 'The password must contain at least one uppercase letter.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'province' => is_string($this->province) ? strtoupper($this->province) : null,
        ]));
    }
}
