<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'checklist_submission_id', 'checklist_question_id', 'value_text', 'value_date',
    'value_time', 'selected_option_ids', 'note_text', 'attachment_path',
])]
class ChecklistAnswer extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'value_date' => 'date',
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
