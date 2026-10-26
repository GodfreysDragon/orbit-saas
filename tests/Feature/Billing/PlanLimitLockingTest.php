<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/*
 * Two requests racing for the last seat or project need two database
 * connections to reproduce, which the in-memory test database can't do.
 * These tests pin down what prevents the race: the organization's row is
 * locked first, and the limit check and the insert run inside that same
 * transaction.
 */

/**
 * Record each query with the transaction depth it ran at.
 *
 * @return Closure(): list<array{sql: string, depth: int}>
 */
function recordQueries(): Closure
{
    $queries = [];

    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = ['sql' => $query->sql, 'depth' => DB::transactionLevel()];
    });

    return function () use (&$queries): array {
        return $queries;
    };
}

/**
 * The index of the first recorded query matching all the fragments.
 *
 * @param  list<array{sql: string, depth: int}>  $queries
 */
function queryIndex(array $queries, string ...$fragments): int
{
    foreach ($queries as $index => $query) {
        if (collect($fragments)->every(fn (string $fragment) => str_contains($query['sql'], $fragment))) {
            return $index;
        }
    }

    throw new RuntimeException('No query matched: '.implode(', ', $fragments));
}

test('creating a project locks the organization before checking the project limit', function () {
    [, $owner] = organizationWithUser();
    $this->actingAs($owner);
    $depth = DB::transactionLevel();
    $queries = recordQueries();

    $this->post(route('projects.store'), ['name' => 'Launch'])->assertRedirect(route('projects.index'));

    $queries = $queries();
    $lock = queryIndex($queries, 'from "organizations"', 'limit 2');
    $check = queryIndex($queries, 'count(*)', 'from "projects"');
    $insert = queryIndex($queries, 'insert into "projects"');

    expect($lock)->toBeLessThan($check)
        ->and($check)->toBeLessThan($insert)
        ->and($queries[$lock]['depth'])->toBeGreaterThan($depth)
        ->and($queries[$insert]['depth'])->toBeGreaterThanOrEqual($queries[$lock]['depth']);
});

test('inviting locks the organization before checking the seat limit', function () {
    Notification::fake();
    [, $owner] = organizationWithUser();
    $this->actingAs($owner);
    $depth = DB::transactionLevel();
    $queries = recordQueries();

    $this->post(route('organization.invitations.store'), ['email' => 'new@example.com', 'role' => 'member'])
        ->assertSessionHasNoErrors();

    $queries = $queries();
    $lock = queryIndex($queries, 'from "organizations"', 'limit 2');
    $check = queryIndex($queries, 'count(*)', 'from "organization_invitations"');
    $insert = queryIndex($queries, 'insert into "organization_invitations"');

    expect($lock)->toBeLessThan($check)
        ->and($check)->toBeLessThan($insert)
        ->and($queries[$lock]['depth'])->toBeGreaterThan($depth)
        ->and($queries[$insert]['depth'])->toBeGreaterThanOrEqual($queries[$lock]['depth']);
});

test('a refused invitation leaves nothing behind', function () {
    Notification::fake();
    // Free plan: 2 seats, both taken.
    [$organization, $owner] = organizationWithUser(trial: false);
    memberOf($organization);

    $this->actingAs($owner)
        ->post(route('organization.invitations.store'), ['email' => 'new@example.com', 'role' => 'member'])
        ->assertSessionHasErrors(['email' => "You've used all seats on the Free plan. Upgrade to invite more members."]);

    expect($organization->invitations()->count())->toBe(0)
        ->and($organization->activities()->where('event', 'member.invited')->count())->toBe(0);
    Notification::assertNothingSent();
});

test('a pending invitation can be sent again when every seat is taken', function () {
    Notification::fake();
    [$organization, $owner] = organizationWithUser(trial: false);

    $this->actingAs($owner)
        ->post(route('organization.invitations.store'), ['email' => 'new@example.com', 'role' => 'member'])
        ->assertSessionHasNoErrors();

    expect($organization->canAddMembers())->toBeFalse();

    $this->actingAs($owner)
        ->post(route('organization.invitations.store'), ['email' => 'new@example.com', 'role' => 'admin'])
        ->assertSessionHasNoErrors();

    expect($organization->invitations()->sole()->role)->toBe(OrganizationRole::Admin);
});

test('the lock is taken on the organization\'s own row', function () {
    $organization = Organization::factory()->create();
    $queries = recordQueries();

    $result = $organization->withLock(fn () => DB::transactionLevel());

    $lock = $queries()[queryIndex($queries(), 'from "organizations"', 'limit 2')];

    expect($result)->toBe($lock['depth'])
        ->and($lock['sql'])->toContain('"organizations"."id" = ?');
});
