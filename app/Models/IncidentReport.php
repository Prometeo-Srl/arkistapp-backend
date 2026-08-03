<?php

namespace App\Models;

use App\Enums\IncidentKind;
use App\Enums\IncidentStatus;
use App\Enums\SeverityBucket;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_id', 'kind', 'is_anonymous', 'reported_by_id', 'occurred_at', 'reported_at',
    'location', 'department', 'description', 'causes', 'actions_taken',
    'injured_person_name', 'absence_days', 'inail_ref', 'status', 'reviewed_by_id', 'closed_at',
])]
class IncidentReport extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'kind' => IncidentKind::class,
            'severity_bucket' => SeverityBucket::class,
            'status' => IncidentStatus::class,
            'is_anonymous' => 'boolean',
            'occurred_at' => 'datetime',
            'reported_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $report) {
            // Una segnalazione anonima non deve restare riconducibile a chi l'ha inviata.
            if ($report->is_anonymous) {
                $report->reported_by_id = null;
            }

            $report->severity_bucket = SeverityBucket::fromAbsenceDays($report->absence_days);
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(IncidentAttachment::class);
    }
}
