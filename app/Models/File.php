<?php

namespace App\Models;

use App\Enums\FileVisibility;
use App\Enums\MediaKind;
use App\Observers\FileObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(FileObserver::class)]
#[Fillable([
    'folder_id', 'document_type_id', 'name', 'media_kind', 'mime_type', 'size_bytes',
    'current_version_id', 'issued_at', 'expires_at', 'requires_acknowledgement', 'requires_signature',
    'owner_user_id', 'uploaded_by_id', 'visibility',
])]
class File extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'media_kind' => MediaKind::class,
            'visibility' => FileVisibility::class,
            'issued_at' => 'date',
            'expires_at' => 'date',
            'requires_acknowledgement' => 'boolean',
            'requires_signature' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(fn (self $file) => $file->recalculateExpiry());
    }

    /**
     * Dynamic document expiry: document date + the validity of its type.
     * An expiry date set by hand is never overwritten.
     */
    public function recalculateExpiry(): void
    {
        if ($this->isDirty('expires_at')) {
            return;
        }

        $months = $this->documentType?->validity_months;

        $this->expires_at = ($this->issued_at && $months)
            ? $this->issued_at->copy()->addMonths($months)
            : null;
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(FileVersion::class)->orderByDesc('version_no');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(FileVersion::class, 'current_version_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function acknowledgements(): HasMany
    {
        return $this->hasMany(Acknowledgement::class);
    }

    public function accessGrants(): MorphMany
    {
        return $this->morphMany(AccessGrant::class, 'grantable');
    }
}
