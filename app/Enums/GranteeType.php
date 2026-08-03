<?php

namespace App\Enums;

enum GranteeType: string
{
    case User = 'user';
    case OrgRole = 'org_role';
}
