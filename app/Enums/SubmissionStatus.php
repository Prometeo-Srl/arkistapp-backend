<?php

namespace App\Enums;

/**
 * Lifecycle of a checklist submission. The controller had been writing the raw
 * strings 'in_progress' and 'completed' while the schema documented draft/submitted:
 * the code is the reality, so these are its values, now the only accepted ones.
 */
enum SubmissionStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
