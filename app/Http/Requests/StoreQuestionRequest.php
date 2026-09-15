<?php

namespace App\Http\Requests;

use App\Support\ChecklistRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** One question at a time. The whole-tree reconciler shares these rules. */
class StoreQuestionRequest extends FormRequest
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
        return ChecklistRules::question(keyed: false);
    }
}
