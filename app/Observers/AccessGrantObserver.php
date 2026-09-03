<?php

namespace App\Observers;

use App\Models\AccessGrant;
use App\Models\File;
use App\Support\AcknowledgementRoster;
use App\Support\Audit;

/**
 * "ha condiviso il file": a share is a grant, and the trail belongs on the node
 * that was shared, not on the grant row that will be revoked and forgotten.
 *
 * Only a grant on a file is recorded — cronologia is a per-document screen, and
 * a folder grant is already visible in "gestisci accesso".
 *
 * The roster of "presa visione"/"firma" is the other half of a share: the duty
 * falls on whoever the document reaches, so writing or revoking a grant changes
 * who owes it — and on a category or folder grant, for every document underneath.
 */
class AccessGrantObserver
{
    public function created(AccessGrant $grant): void
    {
        $file = $grant->grantable;

        if ($file instanceof File) {
            Audit::record('file.shared', $file, $file->folder?->category?->company_id);
        }

        AcknowledgementRoster::syncFor($grant->grantable);
    }

    /** A permission or an expiry moved: what the grant reaches may have moved with it. */
    public function updated(AccessGrant $grant): void
    {
        AcknowledgementRoster::syncFor($grant->grantable);
    }

    public function deleted(AccessGrant $grant): void
    {
        // The row is gone but the morph keys are still on the instance, so the node
        // it pointed at still resolves — which is what the re-sync needs.
        AcknowledgementRoster::syncFor($grant->grantable);
    }
}
