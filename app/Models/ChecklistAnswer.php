<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'checklist_submission_id', 'checklist_question_id', 'value_text', 'value_date',
    'value_time', 'value_number', 'selected_option_ids', 'attachment_path',
])]
class ChecklistAnswer extends Model
{
    protected function casts(): array
    {
        return [
            'value_date' => 'date',
            'value_number' => 'decimal:4',
            'selected_option_ids' => 'array',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ChecklistSubmission::class, 'checklist_submission_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ChecklistQuestion::class, 'checklist_question_id');
    }
}
