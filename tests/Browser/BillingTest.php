<?php

use App\Enums\OrganizationRole;

beforeEach(function () {
    // Checkout itself runs on Stripe's hosted page, so these tests cover the
    // app's own billing UI with Stripe unconfigured.
    config([
        'cashier.key' => null,
        'cashier.secret' => null,
        'billing.plans.pro.stripe_prices.monthly' => 'price_pro_monthly',
    ]);
});

test('the billing page shows the trial and switches between monthly and yearly prices', function () {
    [, $owner] = organizationWithUser();

    $this->actingAs($owner);

    visit('/billing')
        ->assertSee('Your trial ends on')
        ->assertSeeIn('@choose-pro', 'Upgrade to Pro')
        ->assertSee('$29')
        ->assertSee('/ month')
        ->click('@interval-yearly')
        ->assertAttribute('@interval-yearly', 'aria-pressed', 'true')
        ->assertSee('$290')
        ->assertSee('/ year')
        ->assertNoJavaScriptErrors();
});

test('without Stripe keys the page explains why plans cannot be bought', function () {
    [, $owner] = organizationWithUser();

    $this->actingAs($owner);

    visit('/billing')
        ->assertSee('Stripe is not configured')
        ->assertAttribute('@choose-pro', 'disabled', '');
});

test('members without billing access do not see the billing page in the sidebar', function () {
    [, $member] = organizationWithUser(OrganizationRole::Member);

    $this->actingAs($member);

    visit('/dashboard')
        ->assertDontSeeIn('[data-sidebar=sidebar]', 'Billing')
        ->navigate('/billing')
        ->assertSee('403');
});

test('a failed renewal shows a banner that only billing managers can act on', function () {
    [$organization, $owner] = organizationWithUser(trial: false);
    $organization->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_past_due',
        'stripe_status' => 'past_due',
        'stripe_price' => 'price_pro_monthly',
        'quantity' => 1,
    ]);
    $viewer = memberOf($organization, OrganizationRole::Viewer);

    $this->actingAs($owner);
    visit('/projects')
        ->assertSeeIn('@past-due-banner', 'Your last payment failed.')
        ->assertSeeIn('@past-due-banner', 'Update payment method');

    $this->actingAs($viewer);
    visit('/projects')
        ->assertSeeIn('@past-due-banner', "Ask the organization's owner")
        ->assertDontSeeIn('@past-due-banner', 'Update payment method');
});

test('organizations without a subscription show no banner', function () {
    [, $owner] = organizationWithUser(trial: false);

    $this->actingAs($owner);

    visit('/projects')->assertNotPresent('@past-due-banner');
});
