<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['checklist_question_id', 'label', 'image_path', 'position', 'is_non_conformity'])]
class ChecklistOption extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['is_non_conformity' => 'boolean'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ChecklistQuestion::class, 'checklist_question_id');
    }
}
