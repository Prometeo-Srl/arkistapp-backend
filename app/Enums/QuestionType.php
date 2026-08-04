<?php

namespace App\Enums;

enum QuestionType: string
{
    case SingleChoice = 'single_choice';
    case MultiChoice = 'multi_choice';
    case Text = 'text';
    case Date = 'date';
    case Time = 'time';
    case Image = 'image';
    case Number = 'number';

    public function usesOptions(): bool
    {
        return in_array($this, [self::SingleChoice, self::MultiChoice], true);
    }
}
