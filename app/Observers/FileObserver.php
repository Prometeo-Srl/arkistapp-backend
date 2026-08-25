<?php

namespace App\Observers;

use App\Models\File;
use App\Support\Audit;

/**
 * The four file events "cronologia" names (prototype 234): caricato, rinominato,
 * spostato, sostituito — plus the deletion, which the screen never shows because
 * the file it belonged to is gone.
 */
class FileObserver
{
    public function created(File $file): void
    {
        Audit::record('file.uploaded', $file, self::companyId($file));
    }

    public function updated(File $file): void
    {
        $changes = $file->getChanges();
        $companyId = self::companyId($file);

        if (array_key_exists('name', $changes)) {
            Audit::record('file.renamed', $file, $companyId);
        }

        if (array_key_exists('folder_id', $changes)) {
            Audit::record('file.moved', $file, $companyId);
        }

        // The upload writes the row, then points it at its first version: that
        // second write is the same act, not a replacement. Only a version landing
        // on a file that already had one is "sostituito".
        if (array_key_exists('current_version_id', $changes)
            && $file->getOriginal('current_version_id') !== null) {
            Audit::record('file.replaced', $file, $companyId);
        }
    }

    public function deleted(File $file): void
    {
        Audit::record('file.deleted', $file, self::companyId($file));
    }

    /**
     * A file's tenant is three hops up. Loaded rather than read off the row
     * because the column does not exist on `files`.
     */
    private static function companyId(File $file): ?int
    {
        return $file->folder?->category?->company_id;
    }
}
