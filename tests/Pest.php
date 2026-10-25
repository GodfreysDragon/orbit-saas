<?php

use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Browser tests drive the real Vue app in Chromium (Playwright), so they
// need the built assets: run `npm run build` first.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => $this->withVite())
    ->in('Browser');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Create an organization (on a trial unless told otherwise) and a user who
 * belongs to it with the given role and has it as their current organization.
 *
 * @return array{0: Organization, 1: User}
 */
function organizationWithUser(OrganizationRole $role = OrganizationRole::Owner, bool $trial = true): array
{
    $factory = Organization::factory();

    if ($trial) {
        $factory = $factory->onTrial();
    }

    if ($role === OrganizationRole::Owner) {
        $organization = $factory->create();

        return [$organization, $organization->owner];
    }

    $user = User::factory()->create();
    $organization = $factory->withMember($user, $role)->create();
    $user->switchOrganization($organization);

    return [$organization, $user];
}

/**
 * Add a user to an existing organization with a role.
 */
function memberOf(Organization $organization, OrganizationRole $role = OrganizationRole::Member): User
{
    $user = User::factory()->create();

    app(AddOrganizationMember::class)->handle($organization, $user, $role);
    $user->switchOrganization($organization);

    return $user;
}
