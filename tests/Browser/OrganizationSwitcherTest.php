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

    visit('/projects')
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
