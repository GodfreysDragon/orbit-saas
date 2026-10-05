<?php

namespace App\Actions\Organizations;

use App\Models\Activity;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RemoveMember
{
    /**
     * Remove a member (or let a member leave). Authorization, including the
     * rule that the owner can never be removed, lives in OrganizationPolicy.
     */
    public function handle(Organization $organization, User $member, User $actor): void
    {
        DB::transaction(function () use ($organization, $member, $actor): void {
            $member->withinOrganization($organization, fn () => $member->syncRoles([]));

            $organization->users()->detach($member->getKey());

            if ($member->current_organization_id === $organization->getKey()) {
                $member->switchToFallbackOrganization();
            }

            Activity::record(
                $member->is($actor) ? 'member.left' : 'member.removed',
                $member->is($actor) ? "{$member->name} left the organization" : "Removed {$member->name}",
                $member,
                organization: $organization,
                causer: $actor,
            );
        });
    }
}
