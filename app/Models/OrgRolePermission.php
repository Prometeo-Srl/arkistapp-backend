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

    /**
     * The effective flags for a member holding $roleCodes in $company.
     *
     * A missing row is granted, the same default as the column. A member with no
     * appointment at all is a plain lavoratore, so the "lavoratore" switch of 086
     * is what answers for them. Granted if any one held role grants it: an
     * appointment adds sight, it never takes it away.
     *
     * @param  array<int, string>  $roleCodes
     * @return array{can_view_org_chart: bool, can_view_incidents: bool}
     */
    public static function grantedFor(Company $company, array $roleCodes): array
    {
        $codes = $roleCodes === [] ? ['lavoratore'] : array_unique($roleCodes);

        $rows = self::query()
            ->where('company_id', $company->getKey())
            ->with('orgRole')
            ->get()
            ->keyBy(fn (self $permission) => $permission->orgRole?->code);

        $granted = fn (string $flag): bool => collect($codes)
            ->contains(fn (string $code) => $rows->get($code)?->{$flag} ?? true);

        return [
            'can_view_org_chart' => $granted('can_view_org_chart'),
            'can_view_incidents' => $granted('can_view_incidents'),
        ];
    }
}
