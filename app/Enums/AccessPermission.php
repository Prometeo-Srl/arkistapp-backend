<?php

namespace App\Enums;

/**
 * Sharing levels from the XD prototypes (Visualizzatore / Custode / Editor info popups).
 */
enum AccessPermission: string
{
    case Viewer = 'viewer';
    case Custodian = 'custodian';
    case Editor = 'editor';

    public function canDownload(): bool
    {
        return true;
    }

    /** Higher wins when several grants cover the same file through different levels. */
    public function rank(): int
    {
        return match ($this) {
            self::Viewer => 1,
            self::Custodian => 2,
            self::Editor => 3,
        };
    }

    public function canManageExpiry(): bool
    {
        return $this !== self::Viewer;
    }

    public function canWrite(): bool
    {
        return $this === self::Editor;
    }
}
