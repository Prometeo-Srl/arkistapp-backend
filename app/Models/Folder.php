<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'category_id', 'parent_folder_id', 'name', 'icon', 'position',
    'is_personal_of_user_id', 'created_by_id',
])]
class Folder extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Deleting a folder takes its whole subtree with it: sub-folders, their files and
     * every grant that pointed at any of them. Access is resolved from live grants, so
     * revoking them is what actually locks a shared member out of the branch.
     *
     * The recursion runs through this same hook, one level per child.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $folder) {
            $folder->accessGrants()->delete();

            $folder->files->each(function (File $file) {
                $file->accessGrants()->delete();
                $file->delete();
            });

            $folder->children->each(fn (self $child) => $child->delete());
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_folder_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_folder_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    /** When set, this is that worker's individual folder. */
    public function personalOf(): BelongsTo
    {
        return $this->belongsTo(User::class, 'is_personal_of_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function accessGrants(): MorphMany
    {
        return $this->morphMany(AccessGrant::class, 'grantable');
    }
}
