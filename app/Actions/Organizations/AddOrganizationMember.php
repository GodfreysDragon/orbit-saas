<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

class AddOrganizationMember
{
    /**
     * Membership (the pivot) and the role (Spatie, scoped to the organization)
     * are always written together so they can never drift apart.
     */
    public function handle(Organization $organization, User $user, OrganizationRole $role): void
    {
        $organization->users()->syncWithoutDetaching([$user->getKey()]);

        $user->withinOrganization($organization, fn () => $user->syncRoles($role->value));
    }
}
