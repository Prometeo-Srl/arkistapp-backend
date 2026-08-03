<?php

namespace App\Enums;

enum SupportMessageKind: string
{
    case Text = 'text';
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case File = 'file';
    case CallLog = 'call_log';
}
