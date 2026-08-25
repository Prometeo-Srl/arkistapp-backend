<?php

namespace App\Http\Controllers;

use App\Enums\GranteeType;
use App\Http\Requests\IndexAccessGrantRequest;
use App\Http\Requests\StoreAccessGrantRequest;
use App\Http\Resources\AccessGrantResource;
use App\Models\AccessGrant;
use App\Models\Category;
use App\Models\CompanyMembership;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Granular sharing ("Gestisci accesso" / "Condividi"): one grant covers a
 * category, folder or file, for a user or an org chart role.
 */
class AccessGrantController extends Controller
{
    /**
     * The "condiviso con" list of prototype 169: the owner, then every grant.
     *
     * `audience` narrows it to one of the two screens reading this endpoint — see
     * IndexAccessGrantRequest.
     */
    public function index(IndexAccessGrantRequest $request)
    {
        $data = $request->validated();

        $grantable = $this->resolveGrantable($data['grantable_type'], $data['grantable_id']);
        $this->authorize('update', $grantable);

        $company = AccessGrant::companyOf($grantable);

        $grants = AccessGrant::query()
            ->where('grantable_type', $data['grantable_type'])
            ->where('grantable_id', $data['grantable_id'])
            ->with(['granteeUser', 'granteeOrgRole'])
            ->oldest()
            ->get();

        if ($audience = $data['audience'] ?? null) {
            $onOrgChart = CompanyMembership::query()
                ->where('company_id', $company->getKey())
                ->onOrgChart()
                ->pluck('user_id')
                ->all();

            $grants = $grants
                // A role grant belongs to neither screen, and its grantee_id indexes
                // org_roles — comparing it against user ids would misfile it.
                ->where('grantee_type', GranteeType::User)
                ->filter(
                    // A grant still waiting for an account is a guest by construction:
                    // nobody on the org chart lacks a user to key on.
                    fn (AccessGrant $grant) => in_array($grant->grantee_id, $onOrgChart, true)
                        === ($audience === 'org_chart')
                )
                ->values();
        }

        return AccessGrantResource::collection($grants)->additional([
            'owner' => [
                'name' => $company->name,
                'email' => $company->owner?->email,
            ],
        ]);
    }

    public function store(StoreAccessGrantRequest $request)
    {
        $data = $request->validated();

        $grantable = $this->resolveGrantable($data['grantable_type'], $data['grantable_id']);
        $this->authorize('update', $grantable);

        [$granteeId, $invitedEmail] = $this->resolveGrantee($data, $grantable);

        $grant = AccessGrant::updateOrCreate(
            [
                'grantable_type' => $data['grantable_type'],
                'grantable_id' => $data['grantable_id'],
                'grantee_type' => $data['grantee_type'],
                // A pending invite has no grantee to key on — and NULL never matches
                // NULL in the unique index either; the email identifies it instead.
                ...($granteeId === null
                    ? ['invited_email' => $invitedEmail]
                    : ['grantee_id' => $granteeId]),
            ],
            [
                'grantee_id' => $granteeId,
                'invited_email' => $granteeId === null ? $invitedEmail : null,
                'permission' => $data['permission'],
                'granted_by_id' => $request->user()->getKey(),
                'expires_at' => $data['expires_at'] ?? null,
            ]
        );

        return (new AccessGrantResource($grant->load(['granteeUser', 'granteeOrgRole'])))
            ->response()
            ->setStatusCode($grant->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, AccessGrant $grant)
    {
        $grantable = $grant->grantable;
        abort_unless($grantable, 404);

        $this->authorize('update', $grantable);

        $grant->delete();

        return response()->noContent();
    }

    /**
     * "aggiungi persone..": the app knows an email, not an id.
     *
     * Sharing is immediate and asks nothing of the recipient: an existing account is
     * let into the workspace right here, and an address with no account yet is kept
     * on the grant until one appears (see AccessGrant::claimFor).
     *
     * @param  array<string, mixed>  $data
     * @return array{0: ?int, 1: ?string}
     */
    private function resolveGrantee(array $data, Model $grantable): array
    {
        if (($data['grantee_type'] ?? null) !== GranteeType::User->value || empty($data['email'])) {
            return [$data['grantee_id'] ?? null, null];
        }

        $email = $data['email'];
        $user = User::query()->whereRaw('lower(email) = ?', [mb_strtolower($email)])->first();

        if (! $user) {
            return [null, $email];
        }

        CompanyMembership::ensureFor($user, AccessGrant::companyOf($grantable));

        return [$user->getKey(), null];
    }

    private function resolveGrantable(string $type, int $id): Model
    {
        return match ($type) {
            'category' => Category::query()->findOrFail($id),
            'folder' => Folder::query()->findOrFail($id),
            'file' => File::query()->findOrFail($id),
        };
    }
}
