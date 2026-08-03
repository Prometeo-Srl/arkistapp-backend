<?php

namespace App\Enums;

enum IncidentStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Closed = 'closed';
}
