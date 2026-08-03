<?php

namespace App\Models;

use App\Enums\QuestionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'checklist_section_id', 'label', 'help_text', 'type',
    'is_required', 'allows_attachment', 'position',
])]
class ChecklistQuestion extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'is_required' => 'boolean',
            'allows_attachment' => 'boolean',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(ChecklistSection::class, 'checklist_section_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ChecklistOption::class)->orderBy('position');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ChecklistAnswer::class);
    }
}
