<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Http\Requests\AcceptInvitationRequest;
use App\Http\Requests\StoreInvitationRequest;
use App\Http\Resources\InvitationResource;
use App\Http\Resources\MembershipResource;
use App\Models\Company;
use App\Models\Invitation;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function index(Request $request, Company $company)
    {
        $this->authorize('manageInvitations', $company);

        return InvitationResource::collection(
            $company->invitations()->with('orgRole')->latest()->get()
        );
    }

    public function store(StoreInvitationRequest $request, Company $company)
    {
        $this->authorize('manageInvitations', $company);

        $invitation = $company->invitations()->create([
            ...$request->validated(),
            'invited_by_id' => $request->user()->getKey(),
        ]);

        return (new InvitationResource($invitation->load('orgRole')))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, Company $company, Invitation $invitation)
    {
        $this->authorize('manageInvitations', $company);

        $invitation->delete();

        return response()->noContent();
    }

    /** The invitee redeems the emailed token; creates the membership and consumes the invitation. */
    public function accept(AcceptInvitationRequest $request)
    {
        $user = $request->user();
        $invitation = Invitation::query()
            ->where('token', $request->validated('token'))
            ->pending()
            ->firstOrFail();

        abort_unless(
            strtolower($invitation->email) === strtolower($user->email),
            422,
            'This invitation was issued for a different email address.'
        );

        $existing = $invitation->company->memberships()
            ->where('user_id', $user->getKey())
            ->first();

        abort_if(
            $existing?->status === MembershipStatus::Active,
            422,
            'You are already a member of this company.'
        );

        // Archiving and re-inviting is a normal flow ("profili archiviati"), and
        // (company_id, user_id) is unique: reuse the row instead of inserting a second one.
        $attributes = [
            'status' => MembershipStatus::Active,
            'is_admin' => $invitation->is_admin,
            'invited_by_id' => $invitation->invited_by_id,
        ];

        if ($existing) {
            $existing->update($attributes);
            $membership = $existing;
        } else {
            $membership = $invitation->company->memberships()->create([
                'user_id' => $user->getKey(),
                ...$attributes,
            ]);
        }

        if ($invitation->org_role_id) {
            $membership->orgRoles()->attach($invitation->org_role_id, ['appointed_at' => now()->toDateString()]);
        }

        $invitation->update(['accepted_at' => now(), 'accepted_user_id' => $user->getKey()]);

        return (new MembershipResource($membership->load(['user', 'orgRoles', 'company'])))
            ->response()
            ->setStatusCode(201);
    }
}
