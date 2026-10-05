<?php

namespace Database\Seeders;

use App\Actions\Organizations\AddOrganizationMember;
use App\Actions\Organizations\CreateOrganization;
use App\Enums\OrganizationRole;
use App\Models\Activity;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed a demo organization with members in every role, projects and a
     * couple of weeks of activity so the dashboard has something to show.
     */
    public function run(CreateOrganization $createOrganization, AddOrganizationMember $addMember, CurrentOrganization $current): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $owner = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $createOrganization->handle($owner, "Test's Workspace", personal: true);
        $acme = $createOrganization->handle($owner, 'Acme Inc');

        $team = [
            OrganizationRole::Admin->value => User::factory()->create(['name' => 'Ada Admin', 'email' => 'admin@example.com']),
            OrganizationRole::Member->value => User::factory()->create(['name' => 'Max Member', 'email' => 'member@example.com']),
            OrganizationRole::Viewer->value => User::factory()->create(['name' => 'Vera Viewer', 'email' => 'viewer@example.com']),
        ];

        foreach ($team as $role => $user) {
            $createOrganization->handle($user, "{$user->name}'s Workspace", personal: true);
            $addMember->handle($acme, $user, OrganizationRole::from($role));
            $user->switchOrganization($acme);
        }

        $current->runAs($acme, function () use ($acme, $owner, $team): void {
            auth()->setUser($owner);

            Project::factory()
                ->count(8)
                ->for($acme)
                ->create(['created_by_id' => $owner->id]);

            $acme->invitations()->create([
                'email' => 'new.hire@example.com',
                'role' => OrganizationRole::Member,
                'invited_by_id' => $owner->id,
            ]);

            $this->backfillActivity($acme, [$owner, ...array_values($team)]);
        });

        $owner->switchOrganization($acme);
    }

    /**
     * Spread realistic-looking activity over the last two weeks.
     *
     * @param  list<User>  $users
     */
    private function backfillActivity(Organization $organization, array $users): void
    {
        $projects = Project::pluck('name')->all();

        foreach (range(0, 60) as $i) {
            $user = fake()->randomElement($users);
            $project = fake()->randomElement($projects);
            [$event, $description] = fake()->randomElement([
                ['project.updated', "Updated project \"{$project}\""],
                ['project.updated', "Updated project \"{$project}\""],
                ['project.created', "Created project \"{$project}\""],
                ['member.role_updated', "Changed a member's role"],
            ]);

            Activity::create([
                'organization_id' => $organization->id,
                'causer_id' => $user->id,
                'event' => $event,
                'description' => $description,
                'created_at' => now()->subDays(fake()->numberBetween(0, 13))->subMinutes(fake()->numberBetween(0, 1440)),
            ]);
        }
    }
}
