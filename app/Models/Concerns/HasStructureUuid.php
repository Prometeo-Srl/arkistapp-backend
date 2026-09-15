<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Sections, questions and options are keyed by a uuid the builder generated, so
 * that saving the same tree twice reconciles instead of duplicating (ADR-0004).
 *
 * Rows the server creates on its own - a duplicated checklist, a granular POST,
 * a factory - still need one, so it is filled in here when the caller has none.
 */
trait HasStructureUuid
{
    protected static function bootHasStructureUuid(): void
    {
        static::creating(function (self $model) {
            $model->uuid ??= (string) Str::uuid();
        });
    }
}
