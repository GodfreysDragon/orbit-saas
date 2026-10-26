<?php

use App\Enums\OrganizationRole;
use App\Enums\Permission;
use App\Models\Activity;
use App\Models\Organization;
use Inertia\Testing\AssertableInertia as Assert;

test('the owner can transfer ownership to an admin and stays on as an admin', function () {
    [$organization, $owner] = organizationWithUser();
    $admin = memberOf($organization, OrganizationRole::Admin);

    $this->actingAs($owner)
        ->put(route('organization.transfer'), ['member' => $admin->id, 'password' => 'password'])
        ->assertRedirect(route('organization.edit'));

    $organization->refresh();

    expect($organization->isOwnedBy($admin))->toBeTrue()
        ->and($admin->roleIn($organization))->toBe(OrganizationRole::Owner)
        ->and($owner->roleIn($organization))->toBe(OrganizationRole::Admin)
        ->and($admin->hasPermissionIn($organization, Permission::ManageBilling))->toBeTrue()
        ->and($owner->hasPermissionIn($organization, Permission::ManageBilling))->toBeFalse()
        ->and(Activity::withoutGlobalScopes()->where('organization_id', $organization->id)
            ->where('event', 'organization.ownership_transferred')->exists())->toBeTrue();
});

test('the new owner can manage billing and the previous owner can be removed', function () {
    [$organization, $owner] = organizationWithUser();
    $admin = memberOf($organization, OrganizationRole::Admin);

    $this->actingAs($owner)->put(route('organization.transfer'), ['member' => $admin->id, 'password' => 'password']);

    config(['cashier.key' => null, 'cashier.secret' => null]);
    $this->actingAs($admin)->get(route('billing.index'))->assertOk();

    $this->actingAs($admin)
        ->delete(route('members.destroy', $owner))
        ->assertRedirect();

    expect($organization->fresh()->hasUser($owner))->toBeFalse();
});

test('ownership can only go to an admin', function () {
    [$organization, $owner] = organizationWithUser();
    $member = memberOf($organization, OrganizationRole::Member);

    $this->actingAs($owner)
        ->put(route('organization.transfer'), ['member' => $member->id, 'password' => 'password'])
        ->assertForbidden();

    expect($organization->fresh()->isOwnedBy($owner))->toBeTrue();
});

test('only the owner can transfer ownership', function () {
    [$organization] = organizationWithUser();
    $admin = memberOf($organization, OrganizationRole::Admin);
    $otherAdmin = memberOf($organization, OrganizationRole::Admin);

    $this->actingAs($admin)
        ->put(route('organization.transfer'), ['member' => $otherAdmin->id, 'password' => 'password'])
        ->assertForbidden();
});

test('a transfer needs the owner\'s password', function () {
    [$organization, $owner] = organizationWithUser();
    $admin = memberOf($organization, OrganizationRole::Admin);

    $this->actingAs($owner)
        ->put(route('organization.transfer'), ['member' => $admin->id, 'password' => 'wrong-password'])
        ->assertSessionHasErrors('password');

    expect($organization->fresh()->isOwnedBy($owner))->toBeTrue();
});

test('ownership cannot go to someone outside the organization', function () {
    [$organization, $owner] = organizationWithUser();
    [, $outsider] = organizationWithUser(OrganizationRole::Admin);

    $this->actingAs($owner)
        ->put(route('organization.transfer'), ['member' => $outsider->id, 'password' => 'password'])
        ->assertSessionHasErrors('member');

    expect($organization->fresh()->isOwnedBy($owner))->toBeTrue();
});

test('personal organizations cannot be transferred', function () {
    $personal = Organization::factory()->personal()->create();
    $owner = $personal->owner;
    $admin = memberOf($personal, OrganizationRole::Admin);

    $this->actingAs($owner)
        ->put(route('organization.transfer', $personal), ['member' => $admin->id, 'password' => 'password'])
        ->assertForbidden();
});

test('the settings page offers the admins as new owners', function () {
    [$organization, $owner] = organizationWithUser();
    $admin = memberOf($organization, OrganizationRole::Admin);
    memberOf($organization, OrganizationRole::Member);

    $this->actingAs($owner)
        ->get(route('organization.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.transfer', true)
            ->has('admins', 1)
            ->where('admins.0.id', $admin->id));

    $this->actingAs($admin)
        ->get(route('organization.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.transfer', false)
            ->has('admins', 0));
});
