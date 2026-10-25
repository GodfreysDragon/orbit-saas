<?php

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Enums\Permission;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->hasPermissionIn($organization, Permission::UpdateOrganization);
    }

    public function delete(User $user, Organization $organization): Response
    {
        if (! $user->hasPermissionIn($organization, Permission::DeleteOrganization)) {
            return Response::deny();
        }

        return $organization->personal
            ? Response::deny('Your personal organization cannot be deleted.')
            : Response::allow();
    }

    /**
     * Whether the user may hand this organization to someone else at all.
     */
    public function transferOwnership(User $user, Organization $organization): Response
    {
        if (! $organization->isOwnedBy($user)) {
            return Response::deny('Only the owner can transfer ownership.');
        }

        return $organization->personal
            ? Response::deny('Your personal organization cannot be transferred.')
            : Response::allow();
    }

    /**
     * Ownership goes to an existing admin, who already manages the organization.
     */
    public function transferOwnershipTo(User $user, Organization $organization, User $member): Response
    {
        $allowed = $this->transferOwnership($user, $organization);

        if ($allowed->denied()) {
            return $allowed;
        }

        if ($member->is($user)) {
            return Response::deny('You already own this organization.');
        }

        return $member->roleIn($organization) === OrganizationRole::Admin
            ? Response::allow()
            : Response::deny('Ownership can only be transferred to an admin.');
    }

    public function viewMembers(User $user, Organization $organization): bool
    {
        return $user->hasPermissionIn($organization, Permission::ViewMembers);
    }

    public function inviteMember(User $user, Organization $organization): bool
    {
        return $user->hasPermissionIn($organization, Permission::InviteMembers);
    }

    public function updateMember(User $user, Organization $organization, User $member): Response
    {
        if (! $user->hasPermissionIn($organization, Permission::UpdateMembers)) {
            return Response::deny();
        }

        if ($organization->isOwnedBy($member)) {
            return Response::deny("The owner's role cannot be changed.");
        }

        return $member->is($user)
            ? Response::deny('You cannot change your own role.')
            : Response::allow();
    }

    public function removeMember(User $user, Organization $organization, User $member): Response
    {
        if ($organization->isOwnedBy($member)) {
            return Response::deny('The owner cannot be removed from the organization.');
        }

        // Any non-owner member may leave on their own.
        if ($member->is($user)) {
            return Response::allow();
        }

        return $user->hasPermissionIn($organization, Permission::RemoveMembers)
            ? Response::allow()
            : Response::deny();
    }

    public function manageBilling(User $user, Organization $organization): bool
    {
        return $user->hasPermissionIn($organization, Permission::ManageBilling);
    }

    public function viewActivity(User $user, Organization $organization): bool
    {
        return $user->hasPermissionIn($organization, Permission::ViewActivity);
    }
}
