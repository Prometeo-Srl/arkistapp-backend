<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';

    /** Piano individuale assorbito dall'abbonamento dell'azienda a cui il lavoratore è stato associato. */
    case Superseded = 'superseded';

    /** @return array<int, self> */
    public static function entitling(): array
    {
        return [self::Trialing, self::Active, self::PastDue];
    }

    public function entitles(): bool
    {
        return in_array($this, self::entitling(), true);
    }
}
