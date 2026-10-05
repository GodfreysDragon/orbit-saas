<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Organizations\AddOrganizationMember;
use App\Actions\Organizations\RemoveMember;
use App\Enums\OrganizationRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\UpdateMemberRoleRequest;
use App\Models\Activity;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(Request $request, CurrentOrganization $current): Response
    {
        $organization = $current->getOrFail();

        Gate::authorize('viewMembers', $organization);

        $user = $request->user();

        // The Spatie `roles` relation is scoped to the current organization by the middleware.
        $members = $organization->users()
            ->with('roles')
            ->orderBy('name')
            ->get()
            ->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->roleIn($organization)?->toArray(),
                'isOwner' => $organization->isOwnedBy($member),
                'isCurrentUser' => $member->is($user),
                'joinedAt' => $member->membership?->created_at?->toIso8601String(),
                'can' => [
                    'update' => Gate::inspect('updateMember', [$organization, $member])->allowed(),
                    'remove' => Gate::inspect('removeMember', [$organization, $member])->allowed(),
                ],
            ]);

        $plan = $organization->plan();

        return Inertia::render('organizations/Members', [
            'members' => $members,
            'invitations' => Gate::allows('inviteMember', $organization)
                ? $organization->invitations()->with('inviter:id,name')->latest()->get()->map(
                    fn (OrganizationInvitation $invitation) => [
                        'id' => $invitation->id,
                        'email' => $invitation->email,
                        'role' => $invitation->role->toArray(),
                        'invitedBy' => $invitation->inviter?->name,
                        'expiresAt' => $invitation->expires_at->toIso8601String(),
                        'expired' => $invitation->isExpired(),
                    ],
                )
                : [],
            'roles' => array_map(fn (OrganizationRole $role) => $role->toArray(), OrganizationRole::assignable()),
            'seats' => ['used' => $organization->seatsUsed(), 'limit' => $plan->maxMembers, 'plan' => $plan->name],
            'can' => [
                'invite' => Gate::allows('inviteMember', $organization),
                'invitePossible' => $organization->canAddMembers(),
                'manageBilling' => Gate::allows('manageBilling', $organization),
            ],
        ]);
    }

    public function update(UpdateMemberRoleRequest $request, CurrentOrganization $current, User $member, AddOrganizationMember $addMember): RedirectResponse
    {
        $organization = $this->organizationWithMember($current, $member);

        Gate::authorize('updateMember', [$organization, $member]);

        $role = OrganizationRole::from($request->validated('role'));

        $addMember->handle($organization, $member, $role);

        Activity::record('member.role_updated', "Changed {$member->name}'s role to {$role->label()}", $member, [
            'role' => $role->value,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return back();
    }

    public function destroy(Request $request, CurrentOrganization $current, User $member, RemoveMember $removeMember): RedirectResponse
    {
        $organization = $this->organizationWithMember($current, $member);

        Gate::authorize('removeMember', [$organization, $member]);

        $removeMember->handle($organization, $member, $request->user());

        if ($member->is($request->user())) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('You left :name.', ['name' => $organization->name])]);

            return to_route('dashboard');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return back();
    }

    private function organizationWithMember(CurrentOrganization $current, User $member): Organization
    {
        $organization = $current->getOrFail();

        abort_unless($organization->hasUser($member), 404);

        return $organization;
    }
}
