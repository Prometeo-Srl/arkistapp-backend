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
            // No default table when the type is unknown: the existence check would then
            // run against an unrelated table and report a misleading error.
            'grantable_id' => array_filter([
                'required', 'integer',
                $grantableTable ? Rule::exists($grantableTable, 'id') : null,
            ]),
            'grantee_type' => ['required', Rule::in(['user', 'org_role'])],
            'grantee_id' => array_filter([
                'required', 'integer',
                $granteeTable ? Rule::exists($granteeTable, 'id') : null,
            ]),
            'permission' => ['required', Rule::enum(AccessPermission::class)],
            'expires_at' => ['nullable', 'date'],
        ];
    }
}
