<?php

namespace App\Observers;

use App\Models\AccessGrant;
use App\Models\File;
use App\Support\Audit;

/**
 * "ha condiviso il file": a share is a grant, and the trail belongs on the node
 * that was shared, not on the grant row that will be revoked and forgotten.
 *
 * Only a grant on a file is recorded — cronologia is a per-document screen, and
 * a folder grant is already visible in "gestisci accesso".
 */
class AccessGrantObserver
{
    public function created(AccessGrant $grant): void
    {
        $file = $grant->grantable;
        if (! $file instanceof File) {
            return;
        }

        Audit::record('file.shared', $file, $file->folder?->category?->company_id);
    }
}
