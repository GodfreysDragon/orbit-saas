<?php

namespace App\Actions\Organizations;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class DeleteOrganization
{
    public function handle(Organization $organization): void
    {
        // Stop billing first so a failed Stripe call leaves the organization intact.
        if ($organization->subscribed()) {
            $organization->subscription()?->cancelNow();
        }

        $members = $organization->users()->get();

        DB::transaction(function () use ($organization, $members): void {
            DB::table(config('permission.table_names.model_has_roles'))
                ->where(config('permission.column_names.team_foreign_key'), $organization->getKey())
                ->delete();

            $organization->delete();

            foreach ($members as $member) {
                if ($member->current_organization_id === $organization->getKey()) {
                    $member->switchToFallbackOrganization();
                }
            }
        });
    }
}
