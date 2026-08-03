<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\MembershipStatus;
use App\Enums\UserType;
use App\Enums\WorkspaceKind;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name', 'surname', 'email', 'password', 'type', 'phone', 'fiscal_code',
    'birth_date', 'avatar_path', 'locale', 'must_change_password', 'last_login_at',
])]
#[Hidden(['password', 'remember_token'])]
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

    /** Amministratore di sistema: opera su tutti i tenant, non ha membership. */
    public function isOperator(): bool
    {
        return $this->type === UserType::PrometeoOperator;
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyMembership::class);
    }

    /** Più di una quando l'utente segue diverse aziende ("Cambio profilo"). */
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

    /** Aziende vere, escluso il workspace personale. */
    public function businessCompanies(): BelongsToMany
    {
        return $this->activeCompanies()->where('kind', WorkspaceKind::Business);
    }

    public function personalWorkspace(): HasOne
    {
        return $this->hasOne(Company::class, 'owner_user_id')
            ->where('kind', WorkspaceKind::Personal);
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
     * Un abbonamento aziendale copre il lavoratore associato: basta che uno
     * qualsiasi dei suoi workspace attivi (personale o aziendale) sia coperto.
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

    /** Grant di condivisione diretti (esclusi quelli ereditati dai ruoli organigramma). */
    public function directAccessGrants(): HasMany
    {
        return $this->hasMany(AccessGrant::class, 'grantee_id')->where('grantee_type', 'user');
    }
}
