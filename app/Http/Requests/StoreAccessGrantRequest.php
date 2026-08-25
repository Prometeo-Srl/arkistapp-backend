<?php

namespace App\Http\Requests;

use App\Enums\AccessPermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Polymorphic sharing: grantable is a category/folder/file (morph aliases),
 * grantee is a user or an org chart role.
 *
 * "Condividi" addresses a person by email rather than by id, and the address may
 * belong to nobody yet, so `email` is an alternative to `grantee_id` — never a
 * second way of naming a user that already exists in the payload.
 */
class StoreAccessGrantRequest extends FormRequest
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
        $grantableTable = match ($this->input('grantable_type')) {
            'category' => 'categories',
            'folder' => 'folders',
            'file' => 'files',
            default => null,
        };

        $granteeTable = match ($this->input('grantee_type')) {
            'user' => 'users',
            'org_role' => 'org_roles',
            default => null,
        };

        return [
            'grantable_type' => ['required', Rule::in(['category', 'folder', 'file'])],
            // No default table when the type is unknown: the existence check would then
            // run against an unrelated table and report a misleading error.
            'grantable_id' => array_filter([
                'required', 'integer',
                $grantableTable ? Rule::exists($grantableTable, 'id') : null,
            ]),
            'grantee_type' => ['required', Rule::in(['user', 'org_role'])],
            'grantee_id' => array_filter([
                'required_without:email', 'nullable', 'integer',
                $granteeTable ? Rule::exists($granteeTable, 'id') : null,
            ]),
            // Only a person can be invited by address; an org chart role is picked by id.
            'email' => [
                'required_without:grantee_id', 'nullable', 'email',
                Rule::prohibitedIf($this->input('grantee_type') !== 'user'),
            ],
            'permission' => ['required', Rule::enum(AccessPermission::class)],
            'expires_at' => ['nullable', 'date'],
        ];
    }
}
