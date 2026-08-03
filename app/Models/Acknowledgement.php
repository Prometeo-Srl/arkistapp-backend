<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mandatory read receipt. Bound to the version: a new version of the file
 * requires a new confirmation.
 */
#[Fillable([
    'file_id', 'file_version_id', 'user_id', 'required_at',
    'viewed_at', 'confirmed_at', 'signature_path', 'ip_address',
])]
class Acknowledgement extends Model
{
    protected function casts(): array
    {
        return [
            'required_at' => 'datetime',
            'viewed_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function fileVersion(): BelongsTo
    {
        return $this->belongsTo(FileVersion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
