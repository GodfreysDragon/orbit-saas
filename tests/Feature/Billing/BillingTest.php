<?php

use App\Enums\OrganizationRole;
use App\Listeners\RecordSubscriptionActivity;
use App\Models\Activity;
use App\Models\Organization;
use App\Models\Project;
use Laravel\Cashier\Events\WebhookHandled;

/**
 * Simulate what Cashier's webhook controller stores after a successful checkout.
 */
function subscribe(Organization $organization, string $price, string $status = 'active'): void
{
    $organization->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_'.uniqid(),
        'stripe_status' => $status,
        'stripe_price' => $price,
        'quantity' => 1,
        'ends_at' => $status === 'canceled' ? now()->subDay() : null,
    ]);
}

beforeEach(function () {
    config([
        'billing.plans.pro.stripe_prices.monthly' => 'price_pro_monthly',
        'billing.plans.business.stripe_prices.yearly' => 'price_business_yearly',
    ]);
});

test('new organizations get the trial plan', function () {
    $organization = Organization::factory()->onTrial()->create();

    expect($organization->plan()->key)->toBe('pro');
});

test('organizations fall back to the free plan when the trial ends', function () {
    $organization = Organization::factory()->create(['trial_ends_at' => now()->subDay()]);

    expect($organization->plan()->key)->toBe('free');
});

test('the plan is resolved from the subscribed stripe price', function () {
    $organization = Organization::factory()->create();

    subscribe($organization, 'price_business_yearly');

    expect($organization->fresh()->plan()->key)->toBe('business')
        ->and($organization->fresh()->plan()->maxProjects)->toBeNull();
});

test('canceled subscriptions no longer grant the paid plan', function () {
    $organization = Organization::factory()->create();

    subscribe($organization, 'price_pro_monthly', 'canceled');

    expect($organization->fresh()->plan()->key)->toBe('free');
});

test('project creation is blocked at the plan limit with an upgrade message', function () {
    [$organization, $owner] = organizationWithUser(trial: false);
    Project::factory()->count(3)->for($organization)->create();

    $this->actingAs($owner)
        ->post(route('projects.store'), ['name' => 'One too many'])
        ->assertForbidden();

    $this->actingAs($owner)
        ->get(route('projects.index'))
        ->assertInertia(fn ($page) => $page
            ->where('can.create', false)
            ->where('can.createReason', fn ($reason) => str_contains($reason, 'Free plan')));
});

test('upgrading lifts the project limit', function () {
    [$organization, $owner] = organizationWithUser(trial: false);
    Project::factory()->count(3)->for($organization)->create();

    subscribe($organization, 'price_pro_monthly');

    $this->actingAs($owner)
        ->post(route('projects.store'), ['name' => 'Now allowed'])
        ->assertRedirect(route('projects.index'));
});

test('the billing page lists plans and the current subscription', function () {
    config(['cashier.key' => null, 'cashier.secret' => null]);
    [, $owner] = organizationWithUser();

    $this->actingAs($owner)
        ->get(route('billing.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('billing/Index')
            ->has('plans', 3)
            ->where('billing.status', 'trialing')
            ->where('stripeConfigured', false));
});

test('checkout without stripe credentials fails gracefully', function () {
    config(['cashier.key' => null, 'cashier.secret' => null]);
    [, $owner] = organizationWithUser();

    $this->actingAs($owner)
        ->post(route('billing.checkout'), ['plan' => 'pro', 'interval' => 'monthly'])
        ->assertRedirect(route('billing.index'));
});

test('checkout rejects unknown or free plans', function () {
    [, $owner] = organizationWithUser();

    $this->actingAs($owner)
        ->post(route('billing.checkout'), ['plan' => 'free', 'interval' => 'monthly'])
        ->assertSessionHasErrors('plan');
});

test('subscription webhooks are recorded in the activity log', function () {
    $organization = Organization::factory()->create(['stripe_id' => 'cus_123']);

    (new RecordSubscriptionActivity)->handle(new WebhookHandled([
        'type' => 'customer.subscription.created',
        'data' => ['object' => [
            'id' => 'sub_123',
            'customer' => 'cus_123',
            'status' => 'active',
            'items' => ['data' => [['price' => ['id' => 'price_pro_monthly']]]],
        ]],
    ]));

    $activity = Activity::withoutGlobalScopes()->where('organization_id', $organization->id)->sole();

    expect($activity->event)->toBe('billing.subscription.created')
        ->and($activity->description)->toBe('Subscribed to the Pro plan');
});

test('past due subscriptions keep the paid plan while Stripe retries the payment', function () {
    [$organization, $owner] = organizationWithUser(trial: false);
    subscribe($organization, 'price_pro_monthly', 'past_due');

    expect($organization->fresh()->plan()->key)->toBe('pro');

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.organization.billingStatus', 'past_due')
            ->where('auth.organization.plan.key', 'pro'));
});

test('members without billing access also see that the organization is past due', function () {
    [$organization, $viewer] = organizationWithUser(OrganizationRole::Viewer, trial: false);
    subscribe($organization, 'price_pro_monthly', 'past_due');

    $this->actingAs($viewer)
        ->get(route('projects.index'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.organization.billingStatus', 'past_due')
            ->where('auth.organization.permissions', fn ($permissions) => ! collect($permissions)->contains('billing.manage')));
});

test('a failed renewal is recorded in the activity log', function () {
    $organization = Organization::factory()->create(['stripe_id' => 'cus_456']);

    (new RecordSubscriptionActivity)->handle(new WebhookHandled([
        'type' => 'customer.subscription.updated',
        'data' => ['object' => [
            'id' => 'sub_456',
            'customer' => 'cus_456',
            'status' => 'past_due',
            'items' => ['data' => [['price' => ['id' => 'price_pro_monthly']]]],
        ]],
    ]));

    expect(Activity::withoutGlobalScopes()->where('organization_id', $organization->id)->sole()->description)
        ->toBe('Payment failed: the Pro plan is past due');
});
