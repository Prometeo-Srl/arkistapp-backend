<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * "modifica dati personali" — the "dati di accesso" half (prototype 080).
 *
 * Both editable fields are credentials, so either one requires the current
 * password: an unlocked phone must not be enough to take over the account by
 * moving the address the password reset would go to.
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
            'email' => [
                'sometimes',
                'email',
                'max:255',
                // ignore(): resubmitting the unchanged address must not collide
                // with the caller's own row. Soft-deleted accounts are scrubbed
                // to deleted+{id}@prometeo.invalid, so they never collide here.
                Rule::unique('users', 'email')->ignore($this->user()),
            ],
            'password' => ['sometimes', 'confirmed', 'regex:/\p{Lu}/u', Password::min(8)->numbers()],
            'current_password' => [
                Rule::requiredIf(fn () => $this->hasAny(['email', 'password'])),
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
