<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Checklist;

/**
 * Sharing freezes a checklist (ADR-0005), and both write paths - the whole-tree
 * reconciler and the granular endpoints - have to say so the same way, so the
 * guard lives here rather than in a copy per controller.
 *
 * 409 rather than 403: the caller is permitted, the checklist is not in a state
 * that accepts the write.
 */
trait GuardsChecklistDrafts
{
    protected function guardDraft(Checklist $checklist): void
    {
        abort_unless(
            $checklist->isDraft(),
            409,
            'This checklist has been shared and can no longer be edited. Duplicate it to make changes.'
        );
    }
}
