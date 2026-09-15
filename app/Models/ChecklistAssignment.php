<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['checklist_id', 'assignee_user_id', 'due_at', 'status', 'assigned_by_id'])]
class ChecklistAssignment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
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
        return $this->belongsTo(User::class, 'assignee_user_id');
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
