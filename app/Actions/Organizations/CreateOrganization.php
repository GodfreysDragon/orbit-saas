<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationRole;
use App\Models\Activity;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateOrganization
{
    public function __construct(private AddOrganizationMember $addMember) {}

    /**
     * Create an organization owned by the user and make it their current one.
     * Every new organization starts on a card-less trial of the trial plan.
     */
    public function handle(User $owner, string $name, bool $personal = false): Organization
    {
        return DB::transaction(function () use ($owner, $name, $personal): Organization {
            $organization = Organization::create([
                'owner_id' => $owner->getKey(),
                'name' => $name,
                'personal' => $personal,
                'trial_ends_at' => now()->addDays(config('billing.trial_days')),
            ]);

            $this->addMember->handle($organization, $owner, OrganizationRole::Owner);

            $owner->switchOrganization($organization);

            Activity::record(
                'organization.created',
                "Created the organization \"{$organization->name}\"",
                $organization,
                organization: $organization,
                causer: $owner,
            );

            return $organization;
        });
    }
}
