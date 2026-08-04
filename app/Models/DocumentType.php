<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'label', 'kind', 'validity_months',
    'reminder_offsets', 'requires_acknowledgement_default',
])]
class DocumentType extends Model
{
    protected function casts(): array
    {
        return [
            'reminder_offsets' => 'array',
            'requires_acknowledgement_default' => 'boolean',
        ];
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }
}
