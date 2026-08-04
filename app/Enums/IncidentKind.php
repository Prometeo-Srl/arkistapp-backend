<?php

namespace App\Enums;

enum IncidentKind: string
{
    case NearMiss = 'near_miss';
    case Injury = 'injury';
}
