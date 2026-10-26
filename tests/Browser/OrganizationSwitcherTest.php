<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Project;

test('the sidebar switcher moves the user between organizations', function () {
    [$acme, $user] = organizationWithUser();
    $acme->update(['name' => 'Acme Inc']);
    Project::factory()->for($acme)->create(['name' => 'Acme Rocket']);

    $globex = Organization::factory()->onTrial()->withMember($user, OrganizationRole::Viewer)->create(['name' => 'Globex']);
    Project::factory()->for($globex)->create(['name' => 'Globex Moonshot']);

    $this->actingAs($user);

    visit(route('projects.index', absolute: false))
        ->assertSee('Acme Rocket')
        ->assertDontSee('Globex Moonshot')
        ->click('@organization-switcher')
        ->click('Globex')
        ->assertSee('Globex Moonshot')
        ->assertDontSee('Acme Rocket')
        ->assertSeeIn('@organization-switcher', 'Viewer')
        ->assertDontSee('New project')
        ->assertNoJavaScriptErrors();

    expect($user->fresh()->current_organization_id)->toBe($globex->id);
});

test('each browser tab keeps working in its own organization', function () {
    [$acme, $user] = organizationWithUser();
    $acme->update(['name' => 'Acme Inc']);
    Project::factory()->for($acme)->create(['name' => 'Acme Rocket']);

    $globex = Organization::factory()->onTrial()->withMember($user, OrganizationRole::Viewer)->create(['name' => 'Globex']);
    Project::factory()->for($globex)->create(['name' => 'Globex Moonshot']);

    $this->actingAs($user);

    $acmeTab = visit(route('projects.index', $acme, absolute: false))->assertSee('Acme Rocket');

    // A second tab opens the other organization...
    visit(route('projects.index', $globex, absolute: false))
        ->assertSee('Globex Moonshot')
        ->assertSeeIn('@organization-switcher', 'Globex');

    // ...and the first tab's links still lead to the first organization.
    $acmeTab->click('Dashboard')
        ->assertPathIs(route('organization.dashboard', $acme, absolute: false))
        ->assertSeeIn('@organization-switcher', 'Acme Inc')
        ->assertSee('Acme Rocket')
        ->assertDontSee('Globex Moonshot')
        ->assertNoJavaScriptErrors();
});
