<?php

namespace App\Enums;

/**
 * Soglia dei 40 giorni di assenza usata dai prototipi per separare le due liste infortuni.
 */
enum SeverityBucket: string
{
    case Under40Days = 'under_40_days';
    case Over40Days = 'over_40_days';

    public static function fromAbsenceDays(?int $days): ?self
    {
        return $days === null ? null : ($days > 40 ? self::Over40Days : self::Under40Days);
    }
}
