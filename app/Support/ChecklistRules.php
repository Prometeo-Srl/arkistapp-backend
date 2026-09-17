<?php

namespace App\Support;

use App\Enums\QuestionType;
use Illuminate\Validation\Rule;

/**
 * One description of what a question and an option may contain, shared by the
 * whole-tree reconciler and by the granular endpoints (ADR-0004): two write
 * paths to the same tables, one copy of the invariants.
 *
 * `$prefix` is the dotted path the fields sit under - '' for a granular request
 * that posts one question, 'sections.*.questions.*.' for the nested payload.
 *
 * `$keyed` says whether the client-generated uuid is required. The reconciler
 * needs it to match rows; a granular POST creates a single row and the model
 * fills one in.
 */
final class ChecklistRules
{
    /**
     * An image path is a name the upload endpoint minted inside its own
     * directory, never a path the client composes. Without the shape check a
     * `../` value reaches Storage::path() unnormalised in the PDF view and
     * embeds any readable image in the app root — another tenant's incident
     * photo included — in a PDF the caller downloads.
     */
    private const IMAGE_PATH_SHAPE = 'regex:/^checklist-images\/[A-Za-z0-9]+\.[A-Za-z0-9]+$/';

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function question(string $prefix = '', bool $keyed = true): array
    {
        return [
            ...($keyed ? [$prefix.'uuid' => ['required', 'uuid']] : []),
            $prefix.'label' => ['required', 'string', 'max:2000'],
            $prefix.'help_text' => ['nullable', 'string', 'max:2000'],
            $prefix.'type' => ['required', Rule::enum(QuestionType::class)],
            $prefix.'image_path' => ['nullable', 'string', 'max:255', self::IMAGE_PATH_SHAPE],
            $prefix.'is_required' => ['sometimes', 'boolean'],
            $prefix.'allows_attachment' => ['sometimes', 'boolean'],
            $prefix.'allows_note' => ['sometimes', 'boolean'],
            $prefix.'position' => [$keyed ? 'required' : 'sometimes', 'integer', 'min:0'],
            $prefix.'options' => ['nullable', 'array'],
            ...self::option($prefix.'options.*.', $keyed),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function option(string $prefix, bool $keyed = true): array
    {
        return [
            ...($keyed ? [$prefix.'uuid' => ['required', 'uuid']] : []),
            $prefix.'label' => ['required', 'string', 'max:255'],
            $prefix.'image_path' => ['nullable', 'string', 'max:255', self::IMAGE_PATH_SHAPE],
            $prefix.'position' => [$keyed ? 'required' : 'sometimes', 'integer', 'min:0'],
        ];
    }
}
