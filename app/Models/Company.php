<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\WorkspaceKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name', 'kind', 'owner_user_id', 'vat_number', 'tax_code', 'legal_address',
    'ateco_code', 'employees_count', 'logo_path', 'status', 'created_by_operator_id',
])]
class Company extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['kind' => WorkspaceKind::class];
    }

    /**
     * Personal workspace of an unassociated worker: holds their archive and
     * carries their individual subscription.
     */
    public static function personalFor(User $user): self
    {
        $workspace = static::firstOrCreate(
            ['kind' => WorkspaceKind::Personal, 'owner_user_id' => $user->getKey()],
            ['name' => trim($user->name.' '.$user->surname)],
        );

        // The personal workspace goes through a membership too, so permissions,
        // subscriptions and the archive all follow a single path.
        CompanyMembership::firstOrCreate(
            ['company_id' => $workspace->getKey(), 'user_id' => $user->getKey()],
            ['status' => MembershipStatus::Active, 'is_admin' => true],
        );

        return $workspace;
    }

    public function isPersonal(): bool
    {
        return $this->kind === WorkspaceKind::Personal;
    }

    public function scopeBusiness(Builder $query): void
    {
        $query->where('kind', WorkspaceKind::Business);
    }

    public function createdByOperator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_operator_id');
    }

    /** Whoever registered the workspace: the employer or the self-employed worker. */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function admins(): BelongsToMany
    {
        return $this->users()->wherePivot('is_admin', true);
    }

    /** The subscription that unlocks the features, if any. */
    public function entitlingSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->whereIn('status', SubscriptionStatus::entitling())
            ->latestOfMany();
    }

    public function hasEntitlingSubscription(): bool
    {
        return $this->entitlingSubscription()->exists();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyMembership::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_memberships')
            ->using(CompanyMembership::class)
            ->withPivot(['status', 'department', 'employee_code', 'hired_at'])
            ->withTimestamps();
    }

    public function brandingSetting(): HasOne
    {
        return $this->hasOne(BrandingSetting::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function importBatches(): HasMany
    {
        return $this->hasMany(ImportBatch::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(Checklist::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function incidentReports(): HasMany
    {
        return $this->hasMany(IncidentReport::class);
    }

    public function supportThreads(): HasMany
    {
        return $this->hasMany(SupportThread::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
