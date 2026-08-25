<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\MembershipStatus;
use App\Enums\UserType;
use App\Enums\WorkspaceKind;
use App\Observers\UserObserver;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name', 'surname', 'email', 'password', 'type', 'phone', 'fiscal_code',
    'birth_date', 'avatar_path', 'locale', 'must_change_password', 'last_login_at',
])]
#[Hidden(['password', 'remember_token'])]
#[ObservedBy(UserObserver::class)]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'type' => UserType::class,
            'birth_date' => 'date',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /** System administrator: operates across every tenant, holds no membership. */
    public function isOperator(): bool
    {
        return $this->type === UserType::PrometeoOperator;
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyMembership::class);
    }

    /** More than one when the user follows several companies ("Cambio profilo"). */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_memberships')
            ->using(CompanyMembership::class)
            ->withPivot(['status', 'department', 'employee_code', 'hired_at'])
            ->withTimestamps();
    }

    public function activeCompanies(): BelongsToMany
    {
        return $this->companies()->wherePivot('status', MembershipStatus::Active->value);
    }

    /** Real companies, excluding the personal workspace. */
    public function businessCompanies(): BelongsToMany
    {
        return $this->activeCompanies()->where('kind', WorkspaceKind::Business);
    }

    public function personalWorkspace(): HasOne
    {
        return $this->hasOne(Company::class, 'owner_user_id')
            ->where('kind', WorkspaceKind::Personal);
    }

    /**
     * Org role ids the user currently holds. Archiving a membership must end every
     * permission derived from its roles, so the membership status is part of the query:
     * this is the single source for role-derived access (grants and checklist assignments).
     *
     * @return Collection<int, int>
     */
    public function activeOrgRoleIds(?int $companyId = null): Collection
    {
        return MembershipRole::query()
            ->whereNull('revoked_at')
            ->whereHas('membership', function (Builder $query) use ($companyId) {
                $query->where('user_id', $this->getKey())
                    ->where('status', MembershipStatus::Active)
                    ->when($companyId, fn (Builder $q) => $q->where('company_id', $companyId));
            })
            ->pluck('org_role_id');
    }

    /**
     * @return Collection<int, int>
     */
    public function activeCompanyIds(): Collection
    {
        return $this->memberships()
            ->where('status', MembershipStatus::Active)
            ->pluck('company_id');
    }

    public function isMemberOf(Company $company): bool
    {
        return $this->memberships()
            ->where('company_id', $company->getKey())
            ->where('status', MembershipStatus::Active)
            ->exists();
    }

    public function isAdminOf(Company $company): bool
    {
        return $this->memberships()
            ->where('company_id', $company->getKey())
            ->where('status', MembershipStatus::Active)
            ->where('is_admin', true)
            ->exists();
    }

    /**
     * A company subscription covers the associated worker: it is enough that any
     * one of their active workspaces (personal or company) is covered.
     */
    public function hasEntitlingSubscription(): bool
    {
        return $this->activeCompanies()
            ->whereHas('subscriptions', fn (Builder $query) => $query->entitling())
            ->exists();
    }

    public function personalFolders(): HasMany
    {
        return $this->hasMany(Folder::class, 'is_personal_of_user_id');
    }

    public function ownedFiles(): HasMany
    {
        return $this->hasMany(File::class, 'owner_user_id');
    }

    public function acknowledgements(): HasMany
    {
        return $this->hasMany(Acknowledgement::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'assignee_user_id');
    }

    public function incidentReports(): HasMany
    {
        return $this->hasMany(IncidentReport::class, 'reported_by_id');
    }

    public function supportThreads(): HasMany
    {
        return $this->hasMany(SupportThread::class, 'opened_by_id');
    }

    /** Direct sharing grants, excluding those inherited from org chart roles. */
    public function directAccessGrants(): HasMany
    {
        return $this->hasMany(AccessGrant::class, 'grantee_id')->where('grantee_type', 'user');
    }
}
