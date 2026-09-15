<?php

namespace App\Support;

/**
 * Section headings and question labels are the only authored fields that accept
 * markup, and only the three the builder's toolbar can produce plus a line break.
 *
 * strip_tags() drops every other tag but keeps the attributes of the ones it
 * allows, so `<b onclick="...">` would survive it. The second pass rewrites each
 * surviving tag to its bare form. Both the reconciler and the granular endpoints
 * go through here; the PDF renders this string, so it has to be safe in HTML.
 */
final class RichText
{
    private const ALLOWED = '<b><i><u><br>';

    public static function sanitize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return preg_replace(
            '/<(\/?)(b|i|u|br)\b[^>]*>/i',
            '<$1$2>',
            strip_tags($value, self::ALLOWED)
        );
    }
}
