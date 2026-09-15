<?php

namespace App\Enums;

/**
 * The five answer shapes the builder offers: scelta singola, scelta multipla,
 * descrizione, data, orario.
 *
 * There is no Image type - an image on a question is decoration the author pins
 * to it (`image_path`), not a question - and no Number: Text covers it.
 */
enum QuestionType: string
{
    case SingleChoice = 'single_choice';
    case MultiChoice = 'multi_choice';
    case Text = 'text';
    case Date = 'date';
    case Time = 'time';

    public function usesOptions(): bool
    {
        return in_array($this, [self::SingleChoice, self::MultiChoice], true);
    }
}
