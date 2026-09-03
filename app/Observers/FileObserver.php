<?php

namespace App\Observers;

use App\Models\File;
use App\Models\Folder;
use App\Support\AcknowledgementRoster;
use App\Support\Audit;

/**
 * The four file events "cronologia" names (prototype 234): caricato, rinominato,
 * spostato, sostituito — plus the deletion, which the screen never shows because
 * the file it belonged to is gone.
 *
 * Also the one place the "presa visione"/"firma" roster is kept in step with the
 * document: every write that changes who the duty falls on comes through here, so
 * a single hook covers the upload, the metadata PATCH and the new version alike.
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
            // A move with no destination reads as a disappearance: the trail has to
            // name both ends, owner included — the same six folder names repeat once
            // per worker under "documenti personali".
            Audit::record('file.moved', $file, $companyId, [
                'from' => self::describeFolder($file->getOriginal('folder_id')),
                'to' => self::describeFolder($file->folder_id),
            ]);
        }

        // The upload writes the row, then points it at its first version: that
        // second write is the same act, not a replacement. Only a version landing
        // on a file that already had one is "sostituito".
        if (array_key_exists('current_version_id', $changes)
            && $file->getOriginal('current_version_id') !== null) {
            Audit::record('file.replaced', $file, $companyId);
        }

        // Only the writes that move who the duty falls on: a rename would otherwise
        // pay for a per-member access resolution it cannot change the result of.
        $touchesRoster = ['requires_acknowledgement', 'requires_signature', 'visibility',
            'current_version_id', 'folder_id', 'owner_user_id'];

        if (array_intersect($touchesRoster, array_keys($changes))) {
            AcknowledgementRoster::sync($file);
        }
    }

    public function deleted(File $file): void
    {
        Audit::record('file.deleted', $file, self::companyId($file));
    }

    /**
     * The two ends of a move, as the trail needs to show them.
     *
     * The owner is part of the identity, not decoration: "contratti" alone names
     * nine different folders in a workspace with nine workers.
     *
     * @return array{id: int, name: string, owner: string|null}|null
     */
    private static function describeFolder(?int $folderId): ?array
    {
        if ($folderId === null) {
            return null;
        }

        $folder = Folder::with('personalOf')->find($folderId);
        if ($folder === null) {
            return null;
        }

        $owner = $folder->personalOf;

        return [
            'id' => $folder->getKey(),
            'name' => $folder->name,
            // Blank rather than absent for an account with no name on it yet: an
            // empty owner has to read as "no owner", or the sentence says "in
            // contratti di ".
            'owner' => $owner === null ? null : (trim("{$owner->name} {$owner->surname}") ?: null),
        ];
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
