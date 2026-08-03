<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['checklist_id', 'title', 'position'])]
class ChecklistSection extends Model
{
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
