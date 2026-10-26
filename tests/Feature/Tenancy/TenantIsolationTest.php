<?php

use App\Enums\OrganizationRole;
use App\Enums\Permission;
use App\Models\Activity;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Support\CurrentOrganization;
use Inertia\Testing\AssertableInertia as Assert;

test('projects are only listed for the current organization', function () {
    [$organization, $user] = organizationWithUser();
    $other = Organization::factory()->create();

    Project::factory()->for($organization)->create(['name' => 'Mine']);
    Project::factory()->for($other)->create(['name' => 'Theirs']);

    $this->actingAs($user)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/Index')
            ->has('projects.data', 1)
            ->where('projects.data.0.name', 'Mine'));
});

test('another organization\'s project cannot be updated or deleted', function () {
    [, $user] = organizationWithUser();
    $foreign = Project::factory()->for(Organization::factory())->create();

    $this->actingAs($user)
        ->put(route('projects.update', $foreign), ['name' => 'Hijacked'])
        ->assertNotFound();

    $this->actingAs($user)
        ->delete(route('projects.destroy', $foreign))
        ->assertNotFound();

    expect($foreign->fresh()->name)->not->toBe('Hijacked');
});

test('new projects are stamped with the current organization', function () {
    [$organization, $user] = organizationWithUser();

    $this->actingAs($user)
        ->post(route('projects.store'), ['name' => 'Launch'])
        ->assertRedirect(route('projects.index'));

    $project = Project::withoutGlobalScopes()->firstWhere('name', 'Launch');

    expect($project->organization_id)->toBe($organization->id)
        ->and($project->created_by_id)->toBe($user->id);
});

test('each organization is served at its own URL, so tabs don\'t interfere', function () {
    [$first, $user] = organizationWithUser();
    $second = Organization::factory()->withMember($user, OrganizationRole::Member)->create();

    Project::factory()->for($first)->create(['name' => 'First org project']);
    Project::factory()->for($second)->create(['name' => 'Second org project']);

    // One tab works in the second organization...
    $this->actingAs($user)
        ->get(route('projects.index', $second))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.organization.id', $second->id)
            ->where('auth.organization.role.value', 'member')
            ->where('auth.organizationSlug', $second->slug)
            ->where('projects.data.0.name', 'Second org project'));

    // ...while another tab keeps showing the first.
    $this->actingAs($user)
        ->get(route('projects.index', $first))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.organization.id', $first->id)
            ->where('auth.organization.role.value', 'owner')
            ->where('projects.data.0.name', 'First org project'));
});

test('links generated during a request stay in its organization', function () {
    [, $user] = organizationWithUser();
    $second = Organization::factory()->withMember($user, OrganizationRole::Member)->create();

    $this->actingAs($user)
        ->post(route('projects.store', $second), ['name' => 'Launch'])
        ->assertRedirect(route('projects.index', $second));

    expect($second->projects()->sole()->name)->toBe('Launch');
});

test('organizations the user does not belong to are not found', function () {
    [, $user] = organizationWithUser();
    $foreign = Organization::factory()->create();

    $this->actingAs($user)->get(route('projects.index', $foreign))->assertNotFound();
    $this->actingAs($user)->get('/orgs/no-such-organization/projects')->assertNotFound();
});

test('the dashboard entry point opens the organization the user last worked in', function () {
    [$first, $user] = organizationWithUser();
    $second = Organization::factory()->withMember($user, OrganizationRole::Member)->create();

    $this->actingAs($user)->get(route('organization.dashboard', $second))->assertOk();

    expect($user->fresh()->current_organization_id)->toBe($second->id);

    $this->actingAs($user->fresh())
        ->get(route('dashboard'))
        ->assertRedirect(route('organization.dashboard', $second));
});

test('a stale current organization falls back to one the user belongs to', function () {
    [$organization, $user] = organizationWithUser();
    $foreign = Organization::factory()->create();

    $user->forceFill(['current_organization_id' => $foreign->id])->save();

    $this->actingAs($user->fresh())
        ->get(route('dashboard'))
        ->assertRedirect(route('organization.dashboard', $organization));

    expect($user->fresh()->current_organization_id)->toBe($organization->id);
});

test('roles are evaluated per organization', function () {
    $user = User::factory()->create();
    $ownedOrg = Organization::factory()->create(['owner_id' => $user->id]);
    $viewerOrg = Organization::factory()->withMember($user, OrganizationRole::Viewer)->create();

    expect($user->roleIn($ownedOrg))->toBe(OrganizationRole::Owner)
        ->and($user->roleIn($viewerOrg))->toBe(OrganizationRole::Viewer)
        ->and($user->hasPermissionIn($ownedOrg, Permission::DeleteProjects))->toBeTrue()
        ->and($user->hasPermissionIn($viewerOrg, Permission::DeleteProjects))->toBeFalse();
});

test('without a current organization tenant queries match nothing', function () {
    Project::factory()->count(2)->for(Organization::factory())->create();
    Activity::record('test.event', 'Recorded', organization: Organization::factory()->create());

    expect(app(CurrentOrganization::class)->check())->toBeFalse()
        ->and(Project::count())->toBe(0)
        ->and(Project::first())->toBeNull()
        ->and(Activity::count())->toBe(0);
});

test('reading across organizations has to be asked for explicitly', function () {
    Project::factory()->count(2)->for(Organization::factory())->create();
    Project::factory()->count(3)->for(Organization::factory())->create();

    expect(Project::acrossOrganizations()->count())->toBe(5);
});

test('an organization\'s relations return its own rows whichever tenant is current', function () {
    $acme = Organization::factory()->create();
    $globex = Organization::factory()->create();
    Project::factory()->count(2)->for($acme)->create();
    Project::factory()->count(3)->for($globex)->create();

    app(CurrentOrganization::class)->runAs($acme, function () use ($acme, $globex) {
        expect(Project::count())->toBe(2)
            ->and($acme->projects()->count())->toBe(2)
            ->and($globex->projects()->count())->toBe(3);
    });

    expect($globex->projects()->count())->toBe(3);
});
