<?php

namespace App\Enums;

enum MediaKind: string
{
    case Document = 'document';
    case Image = 'image';
    case Audio = 'audio';
    case Video = 'video';
}
