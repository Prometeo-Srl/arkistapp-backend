<?php

namespace App\Http\Requests;

use App\Enums\AccessPermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Polymorphic sharing: grantable is a category/folder/file (morph aliases),
 * grantee is a user or an org chart role.
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
            'grantable_id' => ['required', 'integer', Rule::exists($grantableTable ?? 'categories', 'id')],
            'grantee_type' => ['required', Rule::in(['user', 'org_role'])],
            'grantee_id' => ['required', 'integer', Rule::exists($granteeTable ?? 'users', 'id')],
            'permission' => ['required', Rule::enum(AccessPermission::class)],
            'expires_at' => ['nullable', 'date'],
        ];
    }
}
