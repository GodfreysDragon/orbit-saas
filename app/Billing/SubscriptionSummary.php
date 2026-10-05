<?php

namespace App\Billing;

use App\Models\Organization;

/**
 * A front-end friendly snapshot of an organization's billing state.
 */
final class SubscriptionSummary
{
    /**
     * @return array<string, mixed>
     */
    public static function for(Organization $organization): array
    {
        $plan = $organization->plan();
        $subscription = $organization->subscription();

        $status = match (true) {
            $subscription?->hasIncompletePayment() => 'past_due',
            $subscription?->onGracePeriod() => 'canceling',
            $subscription?->onTrial() => 'trialing',
            (bool) $subscription?->valid() => 'active',
            $organization->onGenericTrial() => 'trialing',
            default => 'free',
        };

        return [
            'plan' => $plan->toArray(),
            'status' => $status,
            'subscribed' => (bool) $subscription?->valid(),
            'interval' => $subscription?->stripe_price ? $plan->intervalFor($subscription->stripe_price) : null,
            'trialEndsAt' => ($subscription->trial_ends_at ?? $organization->trial_ends_at)?->toIso8601String(),
            'endsAt' => $subscription?->ends_at?->toIso8601String(),
            'paymentMethod' => $organization->pm_type ? [
                'brand' => $organization->pm_type,
                'lastFour' => $organization->pm_last_four,
            ] : null,
        ];
    }
}
