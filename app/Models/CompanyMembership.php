<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Observers\CompanyMembershipObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[ObservedBy(CompanyMembershipObserver::class)]
#[Fillable([
    'company_id', 'user_id', 'employee_code', 'department', 'hired_at',
    'status', 'is_admin', 'invited_by_id',
])]
class CompanyMembership extends Pivot
{
    public $incrementing = true;

    protected $table = 'company_memberships';

    protected function casts(): array
    {
        return [
            'hired_at' => 'date',
            'status' => MembershipStatus::class,
            'is_admin' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }

    public function membershipRoles(): HasMany
    {
        return $this->hasMany(MembershipRole::class);
    }

    public function orgRoles(): BelongsToMany
    {
        // Explicit pivot keys: on a standalone Pivot, getForeignKey() cannot infer them.
        return $this->belongsToMany(OrgRole::class, 'membership_roles', 'company_membership_id', 'org_role_id')
            ->using(MembershipRole::class)
            ->withPivot(['appointed_at', 'revoked_at', 'appointment_file_id'])
            ->withTimestamps();
    }
}
