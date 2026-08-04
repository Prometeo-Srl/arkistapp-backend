<?php

namespace App\Enums;

enum ActivityKind: string
{
    case ReadDocument = 'read_document';
    case FillChecklist = 'fill_checklist';
    case RenewCertificate = 'renew_certificate';
}
