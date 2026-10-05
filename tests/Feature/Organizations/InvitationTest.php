<?php

use App\Enums\OrganizationRole;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

test('admins can invite members and an email is queued', function () {
    [$organization, $admin] = organizationWithUser(OrganizationRole::Admin);

    $this->actingAs($admin)
        ->post(route('organization.invitations.store'), ['email' => 'New@Example.com', 'role' => 'member'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $invitation = $organization->invitations()->sole();

    expect($invitation->email)->toBe('new@example.com')
        ->and($invitation->role)->toBe(OrganizationRole::Member);

    Notification::assertSentOnDemand(
        OrganizationInvitationNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'new@example.com',
    );
});

test('members cannot invite', function () {
    [, $member] = organizationWithUser(OrganizationRole::Member);

    $this->actingAs($member)
        ->post(route('organization.invitations.store'), ['email' => 'x@example.com', 'role' => 'member'])
        ->assertForbidden();
});

test('the owner role cannot be granted by invitation', function () {
    [, $owner] = organizationWithUser();

    $this->actingAs($owner)
        ->post(route('organization.invitations.store'), ['email' => 'x@example.com', 'role' => 'owner'])
        ->assertSessionHasErrors('role');
});

test('existing members cannot be invited again', function () {
    [$organization, $owner] = organizationWithUser();
    $member = memberOf($organization);

    $this->actingAs($owner)
        ->post(route('organization.invitations.store'), ['email' => $member->email, 'role' => 'member'])
        ->assertSessionHasErrors('email');
});

test('invitations respect the plan seat limit', function () {
    // Free plan: 2 seats. The owner uses one, the first invitation the other.
    [$organization, $owner] = organizationWithUser(trial: false);

    $this->actingAs($owner)
        ->post(route('organization.invitations.store'), ['email' => 'one@example.com', 'role' => 'member'])
        ->assertSessionHasNoErrors();

    $this->actingAs($owner)
        ->post(route('organization.invitations.store'), ['email' => 'two@example.com', 'role' => 'member'])
        ->assertSessionHasErrors('email');

    expect($organization->invitations()->count())->toBe(1);
});

test('invitees can accept and join with the invited role', function () {
    [$organization] = organizationWithUser();
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);
    $invitation = $organization->invitations()->create(['email' => 'invitee@example.com', 'role' => OrganizationRole::Admin]);

    $this->actingAs($invitee)
        ->get(route('invitations.show', $invitation->token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('invitations/Show')->where('emailMatches', true));

    $this->actingAs($invitee)
        ->post(route('invitations.accept', $invitation->token))
        ->assertRedirect(route('dashboard'));

    expect($organization->hasUser($invitee))->toBeTrue()
        ->and($invitee->roleIn($organization))->toBe(OrganizationRole::Admin)
        ->and($invitee->fresh()->current_organization_id)->toBe($organization->id)
        ->and(OrganizationInvitation::count())->toBe(0);
});

test('invitations can only be accepted by the invited email address', function () {
    [$organization] = organizationWithUser();
    $intruder = User::factory()->create();
    $invitation = $organization->invitations()->create(['email' => 'someone@example.com', 'role' => OrganizationRole::Member]);

    $this->actingAs($intruder)
        ->post(route('invitations.accept', $invitation->token))
        ->assertForbidden();

    expect($organization->hasUser($intruder))->toBeFalse();
});

test('expired invitations cannot be accepted', function () {
    [$organization] = organizationWithUser();
    $invitee = User::factory()->create();
    $invitation = $organization->invitations()->create([
        'email' => $invitee->email,
        'role' => OrganizationRole::Member,
        'expires_at' => now()->subDay(),
    ]);

    $this->actingAs($invitee)
        ->post(route('invitations.accept', $invitation->token))
        ->assertStatus(410);
});

test('unverified users cannot accept invitations', function () {
    [$organization] = organizationWithUser();
    $invitee = User::factory()->unverified()->create();
    $invitation = $organization->invitations()->create(['email' => $invitee->email, 'role' => OrganizationRole::Member]);

    $this->actingAs($invitee)
        ->post(route('invitations.accept', $invitation->token))
        ->assertRedirect(route('verification.notice'));
});

test('invitations can only be revoked within their own organization', function () {
    [, $owner] = organizationWithUser();
    [$otherOrganization] = organizationWithUser();
    $foreign = $otherOrganization->invitations()->create(['email' => 'x@example.com', 'role' => OrganizationRole::Member]);

    $this->actingAs($owner)
        ->delete(route('organization.invitations.destroy', $foreign->id))
        ->assertNotFound();

    expect($foreign->fresh())->not->toBeNull();
});

test('expired invitations are pruned', function () {
    [$organization] = organizationWithUser();
    $organization->invitations()->create(['email' => 'old@example.com', 'role' => OrganizationRole::Member, 'expires_at' => now()->subDay()]);
    $organization->invitations()->create(['email' => 'new@example.com', 'role' => OrganizationRole::Member]);

    $this->artisan('model:prune', ['--model' => [OrganizationInvitation::class]]);

    expect(OrganizationInvitation::pluck('email')->all())->toBe(['new@example.com']);
});
