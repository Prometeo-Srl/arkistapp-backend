<?php

namespace App\Enums;

/**
 * The 40-day absence threshold the prototypes use to split the two injury lists.
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
