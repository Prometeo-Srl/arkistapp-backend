<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Step one of "modifica email" (prototype 080): the new address plus the
 * current password.
 *
 * Nothing is written here — the address is only parked, hashed alongside a
 * one-time code, until POST /auth/me/email/verify proves the mailbox is
 * actually reachable. That is why this is a route of its own rather than a
 * field of PATCH /auth/me: an unverified address would lock the account out of
 * its own password reset.
 */
class RequestEmailChangeRequest extends FormRequest
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
            'email' => [
                'required',
                'email',
                'max:255',
                // ignore(): resubmitting the unchanged address must not collide
                // with the caller's own row. Soft-deleted accounts are scrubbed
                // to deleted+{id}@prometeo.invalid, so they never collide here.
                Rule::unique('users', 'email')->ignore($this->user()),
            ],
            'current_password' => ['required', 'current_password'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'The current password is required to change your credentials.',
        ];
    }
}
