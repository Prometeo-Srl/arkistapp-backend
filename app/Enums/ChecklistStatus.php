<?php

namespace App\Enums;

enum ChecklistStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
