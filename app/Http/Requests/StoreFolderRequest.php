<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFolderRequest extends FormRequest
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
        $company = $this->route('company');

        return [
            'category_id' => [
                'required', 'integer',
                Rule::exists('categories', 'id')->where('company_id', $company->getKey()),
            ],
            'parent_folder_id' => [
                'nullable', 'integer',
                Rule::exists('folders', 'id')->where('category_id', $this->input('category_id')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'integer', 'min:0'],
            'is_personal_of_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }
}
