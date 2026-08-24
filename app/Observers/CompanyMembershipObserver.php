<?php

namespace App\Observers;

use App\Enums\MembershipStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\WorkspaceKind;
use App\Models\Category;
use App\Models\CompanyMembership;
use App\Models\Folder;
use App\Models\Subscription;

class CompanyMembershipObserver
{
    /**
     * The branch every worker gets under "documenti personali", in this order.
     * Only the worker and the company admins ever see it.
     */
    public const PERSONAL_FOLDERS = [
        'curriculum vitae', 'contratti', 'buste paga',
        'attestati di formazione', 'cartella clinica', 'documenti vari',
    ];

    /**
     * A worker joining a company with an active subscription must no longer pay for
     * an individual plan: the subscription of their personal workspace gets absorbed.
     * They also get their personal folder branch, so the archive is never empty.
     */
    public function saved(CompanyMembership $membership): void
    {
        if ($membership->status !== MembershipStatus::Active) {
            return;
        }

        static::supersedePersonalPlan($membership);
        static::seedPersonalFolders($membership);
    }

    /** Reused by the subscribe flow, which covers members that joined before the company subscribed. */
    public static function supersedePersonalPlan(CompanyMembership $membership): void
    {
        $company = $membership->company;

        if (! $company || $company->isPersonal()) {
            return;
        }

        $companySubscription = $company->entitlingSubscription;

        if (! $companySubscription) {
            return;
        }

        Subscription::query()
            ->whereIn('status', SubscriptionStatus::entitling())
            ->whereHas('company', fn ($query) => $query
                ->where('kind', WorkspaceKind::Personal)
                ->where('owner_user_id', $membership->user_id))
            ->each(fn (Subscription $personal) => $personal->supersedeWith($companySubscription));
    }

    /**
     * Idempotent, so it also backfills a membership created before this existed
     * and survives the repeated saves an appointment flow does.
     *
     * Returns the created (or already present) folders, keyed by name.
     *
     * @return array<string, Folder>
     */
    public static function seedPersonalFolders(CompanyMembership $membership): array
    {
        $company = $membership->company;

        if (! $company || ! $membership->user_id) {
            return [];
        }

        $author = $company->owner_user_id ?? $company->created_by_operator_id;

        $category = Category::firstOrCreate(
            ['company_id' => $company->getKey(), 'name' => 'documenti personali'],
            ['icon' => 'folder', 'position' => 0, 'created_by_id' => $author],
        );

        $folders = [];

        foreach (self::PERSONAL_FOLDERS as $position => $name) {
            $folders[$name] = Folder::firstOrCreate(
                [
                    'category_id' => $category->getKey(),
                    'parent_folder_id' => null,
                    'name' => $name,
                    'is_personal_of_user_id' => $membership->user_id,
                ],
                ['position' => $position, 'created_by_id' => $author],
            );
        }

        return $folders;
    }
}
