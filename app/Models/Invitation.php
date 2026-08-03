<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** Invitation of a worker into a company, issued even before they have an account. */
#[Fillable([
    'company_id', 'email', 'token', 'org_role_id', 'is_admin',
    'invited_by_id', 'expires_at', 'accepted_at', 'accepted_user_id',
])]
class Invitation extends Model
{
    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $invitation) {
            $invitation->token ??= Str::random(64);
            $invitation->expires_at ??= now()->addDays(14);
        });
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->expires_at?->isFuture();
    }

    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function orgRole(): BelongsTo
    {
        return $this->belongsTo(OrgRole::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }

    public function acceptedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_user_id');
    }
}
