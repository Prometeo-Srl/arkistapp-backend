<?php

namespace App\Enums;

enum ActivityStatus: string
{
    case Todo = 'todo';
    case Done = 'done';
    case Overdue = 'overdue';
}
