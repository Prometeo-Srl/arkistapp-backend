<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'file_id', 'version_no', 'storage_path', 'size_bytes',
    'checksum', 'uploaded_by_id', 'replaced_reason',
])]
class FileVersion extends Model
{
    public const UPDATED_AT = null;

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function acknowledgements(): HasMany
    {
        return $this->hasMany(Acknowledgement::class);
    }
}
