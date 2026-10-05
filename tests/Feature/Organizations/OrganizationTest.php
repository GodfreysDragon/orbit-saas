<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

test('registration creates a personal organization owned by the new user', function () {
    $this->post(route('register.store'), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $user = User::firstWhere('email', 'jane@example.com');
    $organization = $user->currentOrganization;

    expect($organization->name)->toBe("Jane's Workspace")
        ->and($organization->personal)->toBeTrue()
        ->and($organization->isOwnedBy($user))->toBeTrue()
        ->and($user->roleIn($organization))->toBe(OrganizationRole::Owner)
        ->and($organization->onGenericTrial())->toBeTrue();
});

test('users can create additional organizations', function () {
    [, $user] = organizationWithUser();

    $this->actingAs($user)
        ->post(route('organizations.store'), ['name' => 'Second Co'])
        ->assertRedirect(route('dashboard'));

    $organization = Organization::firstWhere('name', 'Second Co');

    expect($organization->slug)->toBe('second-co')
        ->and($user->fresh()->current_organization_id)->toBe($organization->id)
        ->and($user->roleIn($organization))->toBe(OrganizationRole::Owner);
});

test('slugs are unique', function () {
    $first = Organization::factory()->create(['name' => 'Acme']);
    $second = Organization::factory()->create(['name' => 'Acme']);

    expect($first->slug)->toBe('acme')
        ->and($second->slug)->not->toBe('acme')
        ->and($second->slug)->toStartWith('acme-');
});

test('admins can rename the organization but members cannot', function () {
    [$organization, $admin] = organizationWithUser(OrganizationRole::Admin);
    $member = memberOf($organization);

    $this->actingAs($member)->patch(route('organization.update'), ['name' => 'Nope'])->assertForbidden();
    $this->actingAs($admin)->patch(route('organization.update'), ['name' => 'Renamed'])->assertRedirect();

    expect($organization->fresh()->name)->toBe('Renamed');
});

test('owners can delete an organization after confirming their password', function () {
    [$organization, $owner] = organizationWithUser();
    $personal = Organization::factory()->personal()->create(['owner_id' => $owner->id]);
    $owner->switchOrganization($organization);
    $member = memberOf($organization);

    $this->actingAs($owner)
        ->delete(route('organization.destroy'), ['password' => 'wrong'])
        ->assertSessionHasErrors('password');

    $this->actingAs($owner)
        ->delete(route('organization.destroy'), ['password' => 'password'])
        ->assertRedirect(route('dashboard'));

    expect(Organization::find($organization->id))->toBeNull()
        ->and($owner->fresh()->current_organization_id)->toBe($personal->id)
        ->and($member->fresh()->current_organization_id)->toBeNull();
});

test('personal organizations cannot be deleted', function () {
    $owner = User::factory()->create();
    Organization::factory()->personal()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner->fresh())
        ->delete(route('organization.destroy'), ['password' => 'password'])
        ->assertForbidden();
});

test('admins cannot delete the organization', function () {
    [, $admin] = organizationWithUser(OrganizationRole::Admin);

    $this->actingAs($admin)
        ->delete(route('organization.destroy'), ['password' => 'password'])
        ->assertForbidden();
});
