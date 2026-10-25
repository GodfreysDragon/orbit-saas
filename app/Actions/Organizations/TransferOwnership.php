<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationRole;
use App\Models\Activity;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class TransferOwnership
{
    public function __construct(private AddOrganizationMember $addMember) {}

    /**
     * Make an admin the owner. The previous owner stays on as an admin.
     * Authorization lives in OrganizationPolicy::transferOwnershipTo().
     */
    public function handle(Organization $organization, User $newOwner, User $previousOwner): void
    {
        DB::transaction(function () use ($organization, $newOwner, $previousOwner): void {
            $organization->forceFill(['owner_id' => $newOwner->getKey()])->save();
            $organization->setRelation('owner', $newOwner);

            $this->addMember->handle($organization, $newOwner, OrganizationRole::Owner);
            $this->addMember->handle($organization, $previousOwner, OrganizationRole::Admin);

            Activity::record(
                'organization.ownership_transferred',
                "Transferred ownership to {$newOwner->name}",
                $newOwner,
                organization: $organization,
                causer: $previousOwner,
            );
        });

        $this->syncBillingEmail($organization);
    }

    /**
     * Stripe sends receipts to the owner (Organization::stripeEmail()).
     */
    private function syncBillingEmail(Organization $organization): void
    {
        if (! $organization->hasStripeId() || blank(config('cashier.secret'))) {
            return;
        }

        try {
            $organization->updateStripeCustomer(['email' => $organization->stripeEmail()]);
        } catch (Throwable $e) {
            Log::warning('Unable to update the Stripe customer email after an ownership transfer.', [
                'organization_id' => $organization->getKey(),
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
