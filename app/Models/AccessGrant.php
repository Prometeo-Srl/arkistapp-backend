<?php

namespace App\Models;

use App\Enums\AccessPermission;
use App\Enums\GranteeType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Granular sharing of a category/folder/file with either a user or an org chart
 * role ("Gestisci accesso" / "Condividi" in the prototypes).
 */
#[Fillable([
    'grantable_type', 'grantable_id', 'grantee_type', 'grantee_id',
    'invited_email', 'permission', 'granted_by_id', 'expires_at',
])]
class AccessGrant extends Model
{
    use HasFactory;

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

    /**
     * The company the shared node belongs to. Sharing has to reach it: a grant on a
     * node of a workspace the person is not a member of would resolve to nothing.
     */
    public static function companyOf(Model $grantable): Company
    {
        return match (true) {
            $grantable instanceof Category => $grantable->company,
            $grantable instanceof Folder => $grantable->category->company,
            $grantable instanceof File => $grantable->folder->category->company,
        };
    }

    /**
     * Hands every grant addressed to this user's email over to their fresh account.
     *
     * Sharing asks nothing of the person on the other side — no token, no accepting:
     * an address shared with before it had an account is simply waiting, and the
     * account picks the share up the moment it exists.
     */
    public static function claimFor(User $user): void
    {
        $grants = static::query()
            ->whereNull('grantee_id')
            ->whereRaw('lower(invited_email) = ?', [mb_strtolower($user->email)])
            ->get();

        foreach ($grants as $grant) {
            $grantable = $grant->grantable;

            if (! $grantable) {
                continue;
            }

            CompanyMembership::ensureFor($user, static::companyOf($grantable));
            $grant->update(['grantee_id' => $user->getKey(), 'invited_email' => null]);
        }
    }

    public function scopeActive(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** The user's direct grants plus the grants of every org chart role they hold. */
    public function scopeForUser(Builder $query, User $user, ?int $companyId = null): void
    {
        $roleIds = $user->activeOrgRoleIds($companyId);

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
