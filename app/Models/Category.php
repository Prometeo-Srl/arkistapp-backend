<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'name', 'icon', 'color', 'position', 'created_by_id'])]
class Category extends Model
{
    use SoftDeletes;

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    /** Cartelle di primo livello. */
    public function rootFolders(): HasMany
    {
        return $this->hasMany(Folder::class)->whereNull('parent_folder_id');
    }

    public function accessGrants(): MorphMany
    {
        return $this->morphMany(AccessGrant::class, 'grantable');
    }
}
