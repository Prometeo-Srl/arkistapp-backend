<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Expired = 'expired';
}
