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

test('switching organizations changes which data is visible', function () {
    [$first, $user] = organizationWithUser();
    $second = Organization::factory()->withMember($user, OrganizationRole::Member)->create();

    Project::factory()->for($second)->create(['name' => 'Second org project']);

    $this->actingAs($user)
        ->put(route('organizations.switch', $second))
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->current_organization_id)->toBe($second->id);

    $this->actingAs($user->fresh())
        ->get(route('projects.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.organization.id', $second->id)
            ->where('auth.organization.role.value', 'member')
            ->where('projects.data.0.name', 'Second org project'));
});

test('users cannot switch to an organization they do not belong to', function () {
    [, $user] = organizationWithUser();
    $foreign = Organization::factory()->create();

    $this->actingAs($user)
        ->put(route('organizations.switch', $foreign))
        ->assertForbidden();
});

test('a stale current organization falls back to one the user belongs to', function () {
    [$organization, $user] = organizationWithUser();
    $foreign = Organization::factory()->create();

    $user->forceFill(['current_organization_id' => $foreign->id])->save();

    $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk();

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
