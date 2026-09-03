<?php

namespace App\Enums;

use App\Models\File;

/**
 * The four rows of "filtra per tipologia" (prototype 059_filters), and the four
 * cards of the "monitora le attività" carousel (078).
 *
 * A document's tipologia is the pair of duties written on it, not a column of its
 * own: {@see ActivityKind} never had a "firma" case because nothing was ever
 * reading it.
 */
enum MonitorKind: string
{
    case ReadDocument = 'read_document';
    case Sign = 'sign';
    case ReadAndSign = 'read_and_sign';
    case FillChecklist = 'fill_checklist';

    /** Null when the document asks nothing of anybody — it is not on the board. */
    public static function forFile(File $file): ?self
    {
        return match (true) {
            $file->requires_acknowledgement && $file->requires_signature => self::ReadAndSign,
            $file->requires_signature => self::Sign,
            $file->requires_acknowledgement => self::ReadDocument,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::ReadDocument => 'presa visione',
            self::Sign => 'firma',
            self::ReadAndSign => 'presa visione + firma',
            self::FillChecklist => 'checklist',
        };
    }
}
