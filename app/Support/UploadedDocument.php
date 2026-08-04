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
     * @return array<int, string>
     */
    public static function rules(int $maxKilobytes): array
    {
        return [
            'required',
            'file',
            'max:'.$maxKilobytes,
            'mimetypes:'.implode(',', self::MIME_TYPES),
        ];
    }
}
