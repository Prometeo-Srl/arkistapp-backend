<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'label', 'is_unique_per_company', 'min_required', 'position'])]
class OrgRole extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['is_unique_per_company' => 'boolean'];
    }

    public function membershipRoles(): HasMany
    {
        return $this->hasMany(MembershipRole::class);
    }

    public function memberships(): BelongsToMany
    {
        return $this->belongsToMany(CompanyMembership::class, 'membership_roles', 'org_role_id', 'company_membership_id')
            ->using(MembershipRole::class)
            ->withTimestamps();
    }
}
