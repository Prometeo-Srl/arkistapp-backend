<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** What one org role may see inside one company — "Gestisci autorizzazioni" (086). */
#[Fillable(['company_id', 'org_role_id', 'can_view_org_chart', 'can_view_incidents'])]
class OrgRolePermission extends Model
{
    protected function casts(): array
    {
        return [
            'can_view_org_chart' => 'boolean',
            'can_view_incidents' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function orgRole(): BelongsTo
    {
        return $this->belongsTo(OrgRole::class);
    }
}
