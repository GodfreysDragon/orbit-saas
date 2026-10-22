<?php

use App\Enums\OrganizationRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('members can visit the dashboard of their current organization', function () {
    [$organization, $user] = organizationWithUser();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('auth.organization.id', $organization->id)
            ->where('auth.organization.role.value', 'owner')
            ->where('stats.members', 1)
            ->where('billing.plan.key', 'pro')
            ->has('usage.projects'));
});

test('the dashboard loads its deferred activity props', function () {
    [$organization, $user] = organizationWithUser();

    $this->actingAs($user)->post(route('projects.store'), ['name' => 'Tracked']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('activityTrend')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('activityTrend', 14)
                ->where('activityTrend.13.count', 1)
                ->where('recentActivity.0.event', 'project.created')));
});

test('roles without activity access get no activity props', function () {
    [, $viewer] = organizationWithUser(OrganizationRole::Viewer);

    $this->actingAs($viewer)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activityTrend', null)
            ->where('recentActivity', null));
});

test('users without an organization are asked to create one', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('organizations.create'));
});

test('page guides follow the app.page_guides setting', function (bool $enabled) {
    config(['app.page_guides' => $enabled]);
    [, $user] = organizationWithUser();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('pageGuides', $enabled));
})->with([true, false]);
