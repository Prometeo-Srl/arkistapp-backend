<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Enums\UserType;
use App\Enums\WorkspaceKind;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterCompanyRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\OrgRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

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

    /**
     * "modifica dati personali" — the "dati di accesso" rows (prototype 080).
     *
     * Partial: the screen edits one field at a time, so anything absent is left
     * alone. UpdateProfileRequest is what enforces the current password on the
     * two credential fields.
     */
    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();
        // current_password is the proof of identity, never a column to write.
        $data = collect($request->validated())->except('current_password')->all();

        $user->update($data);

        // A password change logs the other devices out: whoever knew the old one
        // must not keep a live session. The caller's own token survives, so the
        // app stays signed in on the phone that made the change.
        if (array_key_exists('password', $data)) {
            $tokens = $user->tokens();
            // Sanctum hands a keyless TransientToken to session guards and to
            // Sanctum::actingAs, so there is nothing to spare in those cases.
            if (($current = $user->currentAccessToken()) instanceof PersonalAccessToken) {
                $tokens->whereKeyNot($current->getKey());
            }
            $tokens->delete();
        }

        return new UserResource($user->fresh());
    }

    /**
     * The profile picture of the menu (prototype 078).
     *
     * One per user and replaced in place: the previous blob is deleted, so an
     * account never accumulates avatars nobody can reach.
     */
    public function storeAvatar(Request $request)
    {
        $request->validate([
            // Raster only: this endpoint serves what it stores inline, and an SVG
            // would then run its own script on the API origin.
            'avatar' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:4096'],
        ]);

        $user = $request->user();
        $previous = $user->avatar_path;

        $user->update([
            'avatar_path' => Storage::putFile('avatars/'.$user->getKey(), $request->file('avatar')),
        ]);

        if ($previous) {
            Storage::delete($previous);
        }

        return new UserResource($user->fresh());
    }

    /**
     * The bytes of the caller's own avatar, inline: the app draws them with an
     * authenticated image request the way it draws document thumbnails.
     */
    public function avatar(Request $request)
    {
        $path = $request->user()->avatar_path;
        abort_unless($path && Storage::exists($path), 404);

        return Storage::response($path, 'avatar', [
            'X-Content-Type-Options' => 'nosniff',
            // Private, and the path changes on every upload, so the app's own
            // cache key turns over with it.
            'Cache-Control' => 'private, max-age=604800',
        ], 'inline');
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
