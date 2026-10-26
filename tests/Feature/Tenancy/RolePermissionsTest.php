<?php

use App\Enums\OrganizationRole;
use App\Models\Project;

test('viewers can see projects but not create them', function () {
    [, $viewer] = organizationWithUser(OrganizationRole::Viewer);

    $this->actingAs($viewer)->get(route('projects.index'))->assertOk();
    $this->actingAs($viewer)->post(route('projects.store'), ['name' => 'Nope'])->assertForbidden();
});

test('members can create and update projects but not delete them', function () {
    [$organization, $member] = organizationWithUser(OrganizationRole::Member);
    $project = Project::factory()->for($organization)->create();

    $this->actingAs($member)->post(route('projects.store'), ['name' => 'Allowed'])->assertRedirect();
    $this->actingAs($member)->put(route('projects.update', $project), ['name' => 'Renamed'])->assertRedirect();
    $this->actingAs($member)->delete(route('projects.destroy', $project))->assertForbidden();

    expect($project->fresh()->name)->toBe('Renamed');
});

test('admins can delete projects', function () {
    [$organization, $admin] = organizationWithUser(OrganizationRole::Admin);
    $project = Project::factory()->for($organization)->create();

    $this->actingAs($admin)->delete(route('projects.destroy', $project))->assertRedirect();

    expect(Project::withoutGlobalScopes()->find($project->id))->toBeNull();
});

test('only owners can manage billing', function (OrganizationRole $role, int $status) {
    [, $user] = organizationWithUser($role);

    $this->actingAs($user)->get(route('billing.index'))->assertStatus($status);
})->with([
    'owner' => [OrganizationRole::Owner, 200],
    'admin' => [OrganizationRole::Admin, 403],
    'member' => [OrganizationRole::Member, 403],
    'viewer' => [OrganizationRole::Viewer, 403],
]);

test('viewers cannot see the activity log', function () {
    [, $viewer] = organizationWithUser(OrganizationRole::Viewer);

    $this->actingAs($viewer)->get(route('activity.index'))->assertForbidden();
});

test('shared props expose the permissions of the current role', function () {
    [, $member] = organizationWithUser(OrganizationRole::Member);

    $this->actingAs($member)
        ->get(route('organization.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.organization.permissions', fn ($permissions) => collect($permissions)->contains('projects.create')
                && ! collect($permissions)->contains('billing.manage')));
});

test('project changes are recorded in the activity log', function () {
    [$organization, $user] = organizationWithUser();

    $this->actingAs($user)->post(route('projects.store'), ['name' => 'Audited']);

    $this->actingAs($user)
        ->get(route('activity.index'))
        ->assertInertia(fn ($page) => $page
            ->where('activities.data.0.event', 'project.created')
            ->where('activities.data.0.causer.id', $user->id));
});
