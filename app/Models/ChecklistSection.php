<?php

namespace App\Models;

use App\Models\Concerns\HasStructureUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uuid', 'checklist_id', 'title', 'position'])]
class ChecklistSection extends Model
{
    use HasFactory;
    use HasStructureUuid;

    public $timestamps = false;

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ChecklistQuestion::class)->orderBy('position');
    }
}
