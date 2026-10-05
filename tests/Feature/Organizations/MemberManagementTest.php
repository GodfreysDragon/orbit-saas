<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;

test('admins can change a member\'s role', function () {
    [$organization, $admin] = organizationWithUser(OrganizationRole::Admin);
    $member = memberOf($organization, OrganizationRole::Viewer);

    $this->actingAs($admin)
        ->patch(route('members.update', $member), ['role' => 'member'])
        ->assertRedirect();

    expect($member->roleIn($organization))->toBe(OrganizationRole::Member);
});

test('the owner\'s role cannot be changed', function () {
    [$organization, $admin] = organizationWithUser(OrganizationRole::Admin);

    $this->actingAs($admin)
        ->patch(route('members.update', $organization->owner), ['role' => 'viewer'])
        ->assertForbidden();
});

test('members cannot change their own role', function () {
    [, $admin] = organizationWithUser(OrganizationRole::Admin);

    $this->actingAs($admin)
        ->patch(route('members.update', $admin), ['role' => 'viewer'])
        ->assertForbidden();
});

test('admins can remove members, who fall back to another organization', function () {
    [$organization, $admin] = organizationWithUser(OrganizationRole::Admin);
    $member = memberOf($organization);
    $personal = Organization::factory()->personal()->create(['owner_id' => $member->id]);
    $member->switchOrganization($organization);

    $this->actingAs($admin)
        ->delete(route('members.destroy', $member))
        ->assertRedirect();

    expect($organization->hasUser($member))->toBeFalse()
        ->and($member->roleIn($organization))->toBeNull()
        ->and($member->fresh()->current_organization_id)->toBe($personal->id);
});

test('members can leave but cannot remove others', function () {
    [$organization, $member] = organizationWithUser(OrganizationRole::Member);
    $other = memberOf($organization);

    $this->actingAs($member)->delete(route('members.destroy', $other))->assertForbidden();
    $this->actingAs($member)->delete(route('members.destroy', $member))->assertRedirect(route('dashboard'));

    expect($organization->hasUser($member))->toBeFalse()
        ->and($organization->hasUser($other))->toBeTrue();
});

test('the owner cannot be removed or leave', function () {
    [$organization, $owner] = organizationWithUser();
    $admin = memberOf($organization, OrganizationRole::Admin);

    $this->actingAs($admin)->delete(route('members.destroy', $owner))->assertForbidden();
    $this->actingAs($owner)->delete(route('members.destroy', $owner))->assertForbidden();
});

test('members of other organizations cannot be targeted', function () {
    [, $owner] = organizationWithUser();
    [, $outsider] = organizationWithUser(OrganizationRole::Member);

    $this->actingAs($owner)->patch(route('members.update', $outsider), ['role' => 'viewer'])->assertNotFound();
    $this->actingAs($owner)->delete(route('members.destroy', $outsider))->assertNotFound();
});

test('the members page lists members with their roles', function () {
    [$organization, $owner] = organizationWithUser();
    memberOf($organization, OrganizationRole::Viewer);

    $this->actingAs($owner)
        ->get(route('members.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organizations/Members')
            ->has('members', 2)
            ->where('can.invite', true));
});
