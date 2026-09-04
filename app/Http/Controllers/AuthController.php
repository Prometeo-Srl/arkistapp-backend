<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Enums\UserType;
use App\Enums\WorkspaceKind;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterCompanyRequest;
use App\Http\Resources\UserResource;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\OrgRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * "Registrazione" for the employer branch of step 1 ("datore di lavoro").
     * One transaction writes the three rows a tenant needs: the user, the business
     * workspace they own, and the admin membership binding them together.
     *
     * The "lavoratore" branch is not wired yet: it needs a personal workspace
     * (Company::personalFor) and its own prototype, which has not been specified.
     */
    public function register(RegisterCompanyRequest $request)
    {
        $data = $request->validated();

        [$user, $company] = DB::transaction(function () use ($data) {
            $user = User::create([
                'email' => $data['email'],
                'password' => $data['password'],
                'type' => UserType::CompanyUser,
                // Self-chosen password: nothing to force a change of on first login.
                'must_change_password' => false,
            ]);

            $company = Company::create([
                'name' => $data['company_name'],
                'kind' => WorkspaceKind::Business,
                'owner_user_id' => $user->getKey(),
                'vat_number' => $data['vat_number'],
                'legal_address' => $data['legal_address'],
                'postal_code' => $data['postal_code'],
                'city' => $data['city'],
                'province' => $data['province'],
                // Self-registration, as opposed to a workspace opened by an operator.
                'created_by_operator_id' => null,
            ]);

            // Whoever registers the company administers it, regardless of the org chart
            // roles they later assign themselves in "Imposta Organigramma".
            $membership = CompanyMembership::create([
                'company_id' => $company->getKey(),
                'user_id' => $user->getKey(),
                'status' => MembershipStatus::Active,
                'is_admin' => true,
            ]);

            // "Imposta Organigramma" step 2 starts at "secondo ddl", so the primary
            // datore di lavoro is the person who registered: never asked for, always
            // implied. datore_lavoro has min_required 1 and would otherwise be unmet.
            $datoreLavoro = OrgRole::query()->where('code', 'datore_lavoro')->first();
            if ($datoreLavoro) {
                $membership->orgRoles()->attach($datoreLavoro->getKey(), [
                    'appointed_at' => now()->toDateString(),
                ]);
            }

            return [$user, $company];
        });

        return response()->json([
            'token' => $user->createToken($data['device_name'])->plainTextToken,
            'user' => new UserResource($user),
            // The app goes straight into "Imposta Organigramma" for this company.
            'company_id' => $company->getKey(),
        ], Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages(['email' => ['The provided credentials are incorrect']]);
        }

        $token = $user->createToken($request->validated('device_name'))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);

    }

    public function me(Request $request)
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Successful logout'], Response::HTTP_OK);
    }

    /**
     * "Elimina account" from the app menu (prototype 078).
     *
     * A soft delete, not an erasure: memberships, appointments, incident reports and
     * checklist answers are safety records (D.Lgs 81/08) that keep referencing the
     * person. What the deletion does guarantee is that the account is gone from the
     * app's point of view — every token revoked, every membership archived, and the
     * identifying columns scrubbed so the address is free to register again.
     */
    public function destroy(Request $request)
    {
        $user = $request->user();

        // Deleting the owner of a workspace other people still work in would leave it
        // with nobody able to administer it: the admin has to be handed over first.
        $ownsLiveWorkspace = Company::query()
            ->business()
            ->where('owner_user_id', $user->getKey())
            ->whereHas('memberships', fn ($query) => $query
                ->where('status', MembershipStatus::Active)
                ->where('user_id', '!=', $user->getKey()))
            ->exists();

        if ($ownsLiveWorkspace) {
            throw ValidationException::withMessages([
                'account' => ["trasferisci l'amministrazione dell'azienda a un altro utente prima di eliminare l'account"],
            ]);
        }

        DB::transaction(function () use ($user) {
            $user->memberships()->update(['status' => MembershipStatus::Archived]);
            $user->tokens()->delete();

            $user->forceFill([
                'email' => 'deleted+'.$user->getKey().'@prometeo.invalid',
                'name' => null,
                'surname' => null,
                'phone' => null,
                'fiscal_code' => null,
                'birth_date' => null,
                'avatar_path' => null,
            ])->save();

            $user->delete();
        });

        return response()->noContent();
    }
}
