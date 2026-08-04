<?php

namespace App\Enums;

enum WorkspaceKind: string
{
    /** A company registered on the platform. */
    case Business = 'business';

    /** Workspace of an unassociated worker: their own archive, their own subscription. */
    case Personal = 'personal';
}
