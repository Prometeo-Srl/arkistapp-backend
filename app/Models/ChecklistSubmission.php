<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['checklist_assignment_id', 'submitted_by_id', 'started_at', 'submitted_at', 'status'])]
class ChecklistSubmission extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ChecklistAssignment::class, 'checklist_assignment_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ChecklistAnswer::class);
    }
}
