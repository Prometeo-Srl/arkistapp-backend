<?php

namespace App\Enums;

/**
 * Livelli di condivisione dei prototipi XD (popup info Visualizzatore/Custode/Editor).
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

    public function canManageExpiry(): bool
    {
        return $this !== self::Viewer;
    }

    public function canWrite(): bool
    {
        return $this === self::Editor;
    }
}
