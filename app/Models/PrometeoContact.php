<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Rubrica staff Prometeo, visibile a tutti i tenant. */
#[Fillable([
    'user_id', 'display_name', 'role_label', 'email',
    'phone', 'avatar_path', 'position', 'is_visible',
])]
class PrometeoContact extends Model
{
    protected function casts(): array
    {
        return ['is_visible' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
