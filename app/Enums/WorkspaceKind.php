<?php

namespace App\Enums;

enum WorkspaceKind: string
{
    /** Azienda registrata sulla piattaforma. */
    case Business = 'business';

    /** Workspace del lavoratore non associato: suo archivio, suo abbonamento. */
    case Personal = 'personal';
}
