<?php

namespace App\Models;

use App\Enums\ChecklistStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'title', 'description', 'status', 'created_by_id', 'published_at'])]
class Checklist extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => ChecklistStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /** A bozza: the only state in which the structure accepts a write (ADR-0005). */
    public function isDraft(): bool
    {
        return $this->status === ChecklistStatus::Draft;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ChecklistSection::class)->orderBy('position');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ChecklistAssignment::class);
    }
}
