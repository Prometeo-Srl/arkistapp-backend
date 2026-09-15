<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Expired = 'expired';
    /** Withdrawn by the author. Whatever was already answered survives. */
    case Cancelled = 'cancelled';
}
