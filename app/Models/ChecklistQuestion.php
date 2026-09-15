<?php

namespace App\Models;

use App\Enums\QuestionType;
use App\Models\Concerns\HasStructureUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uuid', 'checklist_section_id', 'label', 'help_text', 'type', 'image_path',
    'is_required', 'allows_attachment', 'allows_note', 'position',
])]
class ChecklistQuestion extends Model
{
    use HasFactory;
    use HasStructureUuid;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'is_required' => 'boolean',
            'allows_attachment' => 'boolean',
            'allows_note' => 'boolean',
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

    /**
     * Copies this question, its options and the images pinned to either, under a
     * section. Shared by duplicating a whole checklist and by duplicating a single
     * question in place - done server-side so the images are not re-uploaded.
     *
     * The copy takes fresh uuids: it is a new row in the builder's tree, not the
     * same one, and the reconciler keys on that.
     */
    public function copyInto(int $sectionId, int $position): self
    {
        $copy = self::create([
            'checklist_section_id' => $sectionId,
            'label' => $this->label,
            'help_text' => $this->help_text,
            'type' => $this->type,
            'image_path' => $this->image_path,
            'is_required' => $this->is_required,
            'allows_attachment' => $this->allows_attachment,
            'allows_note' => $this->allows_note,
            'position' => $position,
        ]);

        foreach ($this->options as $option) {
            $copy->options()->create([
                'label' => $option->label,
                'image_path' => $option->image_path,
                'position' => $option->position,
            ]);
        }

        return $copy;
    }
}
