<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Enums\GranteeType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'checklist_id', 'assignee_type', 'assignee_id',
    'due_at', 'status', 'assigned_by_id',
])]
class ChecklistAssignment extends Model
{
    protected function casts(): array
    {
        return [
            'assignee_type' => GranteeType::class,
            'status' => AssignmentStatus::class,
            'due_at' => 'datetime',
        ];
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }

    public function assigneeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function assigneeOrgRole(): BelongsTo
    {
        return $this->belongsTo(OrgRole::class, 'assignee_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }

    public function submission(): HasOne
    {
        return $this->hasOne(ChecklistSubmission::class);
    }
}
