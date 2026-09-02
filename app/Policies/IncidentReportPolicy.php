<?php

namespace App\Policies;

use App\Enums\IncidentKind;
use App\Enums\IncidentStatus;
use App\Models\Company;
use App\Models\IncidentReport;
use App\Models\User;

/**
 * Incidents are tenant-scoped: members may report and follow them, admins (and
 * the operator, via before()) own the review/closure flow.
 *
 * An injury is narrower than that on both ends: the capitolato lets only the
 * datore di lavoro file one, and only the DDL, the RSPP and the medico
 * competente read it. A near miss stays open to every member.
 */
class IncidentReportPolicy
{
    public const EMPLOYER_ROLES = ['datore_lavoro', 'datore_lavoro_secondario'];

    public const INJURY_READER_ROLES = [...self::EMPLOYER_ROLES, 'rspp', 'medico_competente'];

    /** The super admin operates across every tenant. */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isOperator() ? true : null;
    }

    public function viewAny(User $user, Company $company): bool
    {
        return $user->isMemberOf($company);
    }

    /** Whether the injury half of the list is theirs to see at all. */
    public function viewInjuries(User $user, Company $company): bool
    {
        return $user->hasOrgRoleIn($company, self::INJURY_READER_ROLES);
    }

    public function create(User $user, Company $company): bool
    {
        return $user->isMemberOf($company);
    }

    public function createInjury(User $user, Company $company): bool
    {
        return $user->hasOrgRoleIn($company, self::EMPLOYER_ROLES);
    }

    public function view(User $user, IncidentReport $incident): bool
    {
        return $user->isMemberOf($incident->company)
            && ($incident->kind !== IncidentKind::Injury
                || $this->viewInjuries($user, $incident->company));
    }

    public function update(User $user, IncidentReport $incident): bool
    {
        if ($user->isAdminOf($incident->company)) {
            return true;
        }

        // The reporter may refine their own report while it is still a draft.
        return $user->getKey() === $incident->reported_by_id
            && $incident->status === IncidentStatus::Draft;
    }

    public function delete(User $user, IncidentReport $incident): bool
    {
        return $user->isAdminOf($incident->company);
    }
}
