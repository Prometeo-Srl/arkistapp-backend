<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * "modifica dati personali" — the "dati di accesso" half (prototype 080).
 *
 * The password is a credential, so changing it requires the current one: an
 * unlocked phone must not be enough to take over the account.
 *
 * The email is deliberately NOT editable here. It goes through
 * POST /auth/me/email then /auth/me/email/verify, which proves the new mailbox
 * is reachable before writing it — accepting it straight from this endpoint
 * would let a typo lock the account out of its own password reset.
 *
 * The password rules mirror RegisterCompanyRequest — the helper text under the
 * field is the same "minimo 8 caratteri, con una maiuscola e un numero".
 */
class UpdateProfileRequest extends FormRequest
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
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'surname' => ['sometimes', 'nullable', 'string', 'max:255'],
            'password' => ['sometimes', 'confirmed', 'regex:/\p{Lu}/u', Password::min(8)->numbers()],
            'current_password' => [
                Rule::requiredIf(fn () => $this->has('password')),
                'current_password',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'password.regex' => 'The password must contain at least one uppercase letter.',
            'current_password.required' => 'The current password is required to change your credentials.',
        ];
    }
}
