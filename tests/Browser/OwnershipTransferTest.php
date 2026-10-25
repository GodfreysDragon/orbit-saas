<?php

use App\Enums\OrganizationRole;

test('the owner hands the organization to an admin from the settings page', function () {
    [$organization, $owner] = organizationWithUser();
    $admin = memberOf($organization, OrganizationRole::Admin);
    $admin->update(['name' => 'Ada Admin']);

    $this->actingAs($owner);

    visit('/organization/settings')
        ->click('#transfer-member')
        ->click('[role=option]')
        ->assertSeeIn('#transfer-member', 'Ada Admin')
        ->click('[data-test=transfer-ownership] button[type=submit]')
        ->assertSee('Make Ada Admin the owner?')
        ->fill('#transfer-password', 'password')
        ->click('[role=dialog] button[type=submit]')
        ->assertSee('Ada Admin is now the owner')
        ->assertSee('Owned by Ada Admin')
        ->assertNotPresent('@transfer-ownership')
        ->assertNoJavaScriptErrors();

    expect($organization->fresh()->isOwnedBy($admin))->toBeTrue()
        ->and($owner->roleIn($organization))->toBe(OrganizationRole::Admin);
});
