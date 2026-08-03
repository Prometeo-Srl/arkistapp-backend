<?php

namespace App\Models;

use App\Enums\AccessPermission;
use App\Enums\GranteeType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Granular sharing of a category/folder/file with either a user or an org chart
 * role ("Gestisci accesso" / "Condividi" in the prototypes).
 */
#[Fillable([
    'grantable_type', 'grantable_id', 'grantee_type', 'grantee_id',
    'permission', 'granted_by_id', 'expires_at',
])]
class AccessGrant extends Model
{
    protected function casts(): array
    {
        return [
            'grantee_type' => GranteeType::class,
            'permission' => AccessPermission::class,
            'expires_at' => 'datetime',
        ];
    }

    public function grantable(): MorphTo
    {
        return $this->morphTo();
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_id');
    }

    public function granteeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'grantee_id');
    }

    public function granteeOrgRole(): BelongsTo
    {
        return $this->belongsTo(OrgRole::class, 'grantee_id');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** The user's direct grants plus the grants of every org chart role they hold. */
    public function scopeForUser(Builder $query, User $user, ?int $companyId = null): void
    {
        $roleIds = MembershipRole::query()
            ->whereNull('revoked_at')
            ->whereHas('membership', function (Builder $q) use ($user, $companyId) {
                $q->where('user_id', $user->getKey())
                    ->when($companyId, fn (Builder $q) => $q->where('company_id', $companyId));
            })
            ->pluck('org_role_id');

        $query->where(function (Builder $q) use ($user, $roleIds) {
            $q->where(fn (Builder $q) => $q
                ->where('grantee_type', GranteeType::User)
                ->where('grantee_id', $user->getKey()));

            if ($roleIds->isNotEmpty()) {
                $q->orWhere(fn (Builder $q) => $q
                    ->where('grantee_type', GranteeType::OrgRole)
                    ->whereIn('grantee_id', $roleIds));
            }
        });
    }
}
