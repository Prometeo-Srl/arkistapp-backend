<?php

namespace App\Models;

use App\Models\Concerns\HasStructureUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uuid', 'checklist_question_id', 'label', 'image_path', 'position'])]
class ChecklistOption extends Model
{
    use HasFactory;
    use HasStructureUuid;

    public $timestamps = false;

    public function question(): BelongsTo
    {
        return $this->belongsTo(ChecklistQuestion::class, 'checklist_question_id');
    }
}
