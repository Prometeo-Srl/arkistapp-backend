<?php

namespace App\Policies;

use App\Enums\IncidentStatus;
use App\Models\Company;
use App\Models\IncidentReport;
use App\Models\User;

/**
 * Incidents are tenant-scoped: members may report and follow them, admins (and
 * the operator, via before()) own the review/closure flow.
 */
class IncidentReportPolicy
{
    /** The super admin operates across every tenant. */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isOperator() ? true : null;
    }

    public function viewAny(User $user, Company $company): bool
    {
        return $user->isMemberOf($company);
    }

    public function create(User $user, Company $company): bool
    {
        return $user->isMemberOf($company);
    }

    public function view(User $user, IncidentReport $incident): bool
    {
        return $user->isMemberOf($incident->company);
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
