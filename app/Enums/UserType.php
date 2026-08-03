<?php

namespace App\Enums;

enum UserType: string
{
    case PrometeoOperator = 'prometeo_operator';
    case CompanyUser = 'company_user';
}
