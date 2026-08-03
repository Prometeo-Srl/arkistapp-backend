<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable([
    'company_membership_id', 'org_role_id', 'appointed_at', 'revoked_at', 'appointment_file_id',
])]
class MembershipRole extends Pivot
{
    public $incrementing = true;

    protected $table = 'membership_roles';

    protected function casts(): array
    {
        return [
            'appointed_at' => 'date',
            'revoked_at' => 'date',
        ];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(CompanyMembership::class, 'company_membership_id');
    }

    public function orgRole(): BelongsTo
    {
        return $this->belongsTo(OrgRole::class);
    }

    /** Appointment letter stored as a document file. */
    public function appointmentFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'appointment_file_id');
    }
}
