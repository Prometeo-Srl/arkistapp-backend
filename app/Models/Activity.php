<?php

namespace App\Models;

use App\Enums\ActivityKind;
use App\Enums\ActivityStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Riga di "Monitora attività": documento da leggere, checklist da compilare,
 * attestato da rinnovare.
 */
#[Fillable([
    'company_id', 'subject_type', 'subject_id', 'assignee_user_id',
    'kind', 'status', 'due_at', 'completed_at',
])]
class Activity extends Model
{
    protected function casts(): array
    {
        return [
            'kind' => ActivityKind::class,
            'status' => ActivityStatus::class,
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [ActivityStatus::Todo, ActivityStatus::Overdue]);
    }
}
