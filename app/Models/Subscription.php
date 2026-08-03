<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id', 'plan_id', 'status', 'started_at',
    'current_period_end', 'canceled_at', 'superseded_by_id', 'provider_ref',
])]
class Subscription extends Model
{
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'started_at' => 'datetime',
            'current_period_end' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    public function scopeEntitling(Builder $query): void
    {
        $query->whereIn('status', SubscriptionStatus::entitling());
    }

    /** Closes this individual plan, now covered by the company subscription. */
    public function supersedeWith(self $companySubscription): void
    {
        $this->update([
            'status' => SubscriptionStatus::Superseded,
            'canceled_at' => now(),
            'superseded_by_id' => $companySubscription->getKey(),
        ]);
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
