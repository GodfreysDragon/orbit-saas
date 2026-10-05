<?php

namespace App\Actions\Organizations;

use App\Models\Activity;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcceptInvitation
{
    public function __construct(private AddOrganizationMember $addMember) {}

    public function handle(OrganizationInvitation $invitation, User $user): Organization
    {
        return DB::transaction(function () use ($invitation, $user): Organization {
            $organization = $invitation->organization;

            $this->addMember->handle($organization, $user, $invitation->role);

            $invitation->delete();

            $user->switchOrganization($organization);

            Activity::record(
                'member.joined',
                "{$user->name} joined as {$invitation->role->label()}",
                $user,
                ['role' => $invitation->role->value],
                $organization,
                $user,
            );

            return $organization;
        });
    }
}
