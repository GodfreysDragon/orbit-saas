<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Organizations\InviteMember;
use App\Enums\OrganizationRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\InviteMemberRequest;
use App\Models\Activity;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class InvitationController extends Controller
{
    public function store(InviteMemberRequest $request, CurrentOrganization $current, InviteMember $inviteMember): RedirectResponse
    {
        $organization = $current->getOrFail();

        Gate::authorize('inviteMember', $organization);

        $invitation = $inviteMember->handle(
            $organization,
            $request->user(),
            $request->validated('email'),
            OrganizationRole::from($request->validated('role')),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent to :email.', ['email' => $invitation->email])]);

        return back();
    }

    public function destroy(CurrentOrganization $current, int $invitation): RedirectResponse
    {
        $organization = $current->getOrFail();

        Gate::authorize('inviteMember', $organization);

        // Resolving through the relation guarantees the invitation belongs to this tenant.
        $invitation = $organization->invitations()->findOrFail($invitation);
        $invitation->delete();

        Activity::record('member.invitation_revoked', "Revoked the invitation for {$invitation->email}");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation revoked.')]);

        return back();
    }
}
