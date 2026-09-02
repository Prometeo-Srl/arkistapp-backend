<?php

namespace App\Support;

/**
 * Single allowlist for every upload endpoint. Validation checks the sniffed MIME
 * type, not the extension, so renaming payload.html to payload.pdf does not pass.
 *
 * Downloads are always served as attachments with nosniff (see FileController),
 * because an allowlist alone still lets a crafted SVG or PDF script run when the
 * browser is allowed to render it inline on the API origin.
 */
final class UploadedDocument
{
    /** @var array<int, string> */
    public const MIME_TYPES = [
        // Documents
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'text/plain',
        'text/csv',
        // Images: no SVG, it is a script container.
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/heic',
        'image/heif',
        // Audio, for the prototype's voice notes
        'audio/mpeg',
        'audio/mp4',
        'audio/aac',
        'audio/wav',
        'audio/x-wav',
        'audio/ogg',
        'audio/webm',
        // Video
        'video/mp4',
        'video/quicktime',
        'video/webm',
    ];

    /**
     * "eventuali referti, verbali o documenti" (capitolato): the slice an incident
     * attachment accepts — a referto as PDF or Word, or a photo of one.
     *
     * Audio and video are left out on purpose: nothing in the spec asks for them,
     * and against that endpoint's 10 MB cap they are the files most likely to cost
     * the reporter a long upload and then bounce.
     *
     * @var array<int, string>
     */
    public const INCIDENT_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.oasis.opendocument.text',
        'image/jpeg',
        'image/png',
        'image/heic',
        'image/heif',
    ];

    /**
     * The cap the app promises the user ("invia file fino a 1 gb", prototype 260).
     *
     * PHP refuses a body over its own post_max_size before validation ever runs,
     * so this has to stay in step with docker/php.ini.
     */
    public const MAX_KILOBYTES = 1048576;

    /**
     * @param  array<int, string>  $mimeTypes
     * @return array<int, string>
     */
    public static function rules(int $maxKilobytes = self::MAX_KILOBYTES, array $mimeTypes = self::MIME_TYPES): array
    {
        return [
            'required',
            'file',
            'max:'.$maxKilobytes,
            'mimetypes:'.implode(',', $mimeTypes),
        ];
    }
}
