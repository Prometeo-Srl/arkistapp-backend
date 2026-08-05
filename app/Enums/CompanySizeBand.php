<?php

namespace App\Enums;

/**
 * "Imposta Organigramma" step 1 asks for a band, not a headcount, so the exact
 * employees_count stays null until something actually collects it.
 */
enum CompanySizeBand: string
{
    case Micro = 'micro';
    case Piccola = 'piccola';
    case Media = 'media';
    case Grande = 'grande';

    /** The worker range each band is labelled with in the prototype. */
    public function label(): string
    {
        return match ($this) {
            self::Micro => '1-10 lavoratori',
            self::Piccola => '11-50 lavoratori',
            self::Media => '51-250 lavoratori',
            self::Grande => '251 + lavoratori',
        };
    }
}
