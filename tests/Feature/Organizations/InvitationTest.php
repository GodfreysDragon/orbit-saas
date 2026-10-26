<?php

use App\Actions\Organizations\InviteMember;
use App\Enums\OrganizationRole;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
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
        ->get(route('invitations.show', $invitation->plainToken))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('invitations/Show')->where('emailMatches', true));

    $this->actingAs($invitee)
        ->post(route('invitations.accept', $invitation->plainToken))
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
        ->post(route('invitations.accept', $invitation->plainToken))
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
        ->post(route('invitations.accept', $invitation->plainToken))
        ->assertStatus(410);
});

test('accepting an invitation verifies the invited email address', function () {
    [$organization] = organizationWithUser();
    $invitee = User::factory()->unverified()->create();
    $invitation = $organization->invitations()->create(['email' => $invitee->email, 'role' => OrganizationRole::Member]);

    $this->actingAs($invitee)
        ->post(route('invitations.accept', $invitation->plainToken))
        ->assertRedirect(route('dashboard'));

    expect($invitee->fresh()->hasVerifiedEmail())->toBeTrue()
        ->and($organization->hasUser($invitee))->toBeTrue();
});

test('unverified users cannot accept invitations sent to another address', function () {
    [$organization] = organizationWithUser();
    $intruder = User::factory()->unverified()->create();
    $invitation = $organization->invitations()->create(['email' => 'someone@example.com', 'role' => OrganizationRole::Member]);

    $this->actingAs($intruder)
        ->post(route('invitations.accept', $invitation->plainToken))
        ->assertForbidden();

    expect($intruder->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('guests see the invitation and return to it after logging in', function () {
    [$organization] = organizationWithUser();
    $invitation = $organization->invitations()->create(['email' => 'new@example.com', 'role' => OrganizationRole::Member]);

    $this->get(route('invitations.show', $invitation->plainToken))
        ->assertOk()
        ->assertSessionHas('url.intended', route('invitations.show', $invitation->plainToken))
        ->assertInertia(fn ($page) => $page
            ->component('auth/Invitation')
            ->where('invitation.organization.name', $organization->name)
            ->where('accountExists', false));
});

test('the sign-up form is prefilled from an invitation', function () {
    [$organization] = organizationWithUser();
    $invitation = $organization->invitations()->create(['email' => 'new@example.com', 'role' => OrganizationRole::Member]);

    $this->get(route('register', ['invitation' => $invitation->plainToken]))
        ->assertInertia(fn ($page) => $page
            ->component('auth/Register')
            ->where('invitation.email', 'new@example.com')
            ->where('invitation.organization', $organization->name));
});

test('signing up from an invitation verifies the email and joins the organization', function () {
    [$organization] = organizationWithUser();
    $invitation = $organization->invitations()->create(['email' => 'new@example.com', 'role' => OrganizationRole::Admin]);

    $this->withSession(['url.intended' => route('invitations.show', $invitation->plainToken)])
        ->post(route('register.store'), [
            'name' => 'New Hire',
            'email' => 'New@Example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invitation' => $invitation->plainToken,
        ])
        ->assertRedirect(route('dashboard'));

    // Fortify lowercases the email on registration.
    $user = User::where('email', 'new@example.com')->sole();

    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->roleIn($organization))->toBe(OrganizationRole::Admin)
        ->and($user->current_organization_id)->toBe($organization->id)
        ->and(OrganizationInvitation::count())->toBe(0);

    Notification::assertNotSentTo($user, VerifyEmail::class);
});

test('signing up with someone else\'s invitation does not verify or join', function () {
    [$organization] = organizationWithUser();
    $invitation = $organization->invitations()->create(['email' => 'invitee@example.com', 'role' => OrganizationRole::Member]);

    $this->post(route('register.store'), [
        'name' => 'Intruder',
        'email' => 'intruder@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'invitation' => $invitation->plainToken,
    ]);

    $user = User::where('email', 'intruder@example.com')->sole();

    expect($user->hasVerifiedEmail())->toBeFalse()
        ->and($organization->hasUser($user))->toBeFalse()
        ->and($invitation->fresh())->not->toBeNull();

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('expired invitations are ignored at sign-up', function () {
    [$organization] = organizationWithUser();
    $invitation = $organization->invitations()->create([
        'email' => 'late@example.com',
        'role' => OrganizationRole::Member,
        'expires_at' => now()->subDay(),
    ]);

    $this->post(route('register.store'), [
        'name' => 'Late Hire',
        'email' => 'late@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'invitation' => $invitation->plainToken,
    ]);

    $user = User::where('email', 'late@example.com')->sole();

    expect($user->hasVerifiedEmail())->toBeFalse()
        ->and($organization->hasUser($user))->toBeFalse();
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

test('only a hash of the invitation token is stored', function () {
    [$organization, $owner] = organizationWithUser();

    $this->actingAs($owner)
        ->post(route('organization.invitations.store'), ['email' => 'new@example.com', 'role' => 'member']);

    $token = null;
    Notification::assertSentOnDemand(
        OrganizationInvitationNotification::class,
        function (OrganizationInvitationNotification $notification) use (&$token) {
            $token = $notification->token;

            return true;
        },
    );

    $stored = $organization->invitations()->sole()->token;

    expect($stored)->not->toBe($token)
        ->and($stored)->toBe(hash('sha256', $token));

    auth()->logout();

    $this->get(route('invitations.show', $token))->assertOk();
    $this->get(route('invitations.show', $stored))->assertNotFound();
});

test('inviting the same address again replaces the old link', function () {
    [$organization, $owner] = organizationWithUser();
    $first = app(InviteMember::class)->handle($organization, $owner, 'new@example.com', OrganizationRole::Member);
    $second = app(InviteMember::class)->handle($organization, $owner, 'new@example.com', OrganizationRole::Admin);

    expect($organization->invitations()->count())->toBe(1);

    $this->get(route('invitations.show', $first->plainToken))->assertNotFound();
    $this->get(route('invitations.show', $second->plainToken))->assertOk();
});

test('the migration hashes tokens that were stored in plain text', function () {
    [$organization] = organizationWithUser();
    $plain = OrganizationInvitation::newToken();
    $invitation = $organization->invitations()->create([
        'email' => 'old@example.com',
        'role' => OrganizationRole::Member,
        'token' => $plain,
    ]);

    (require database_path('migrations/2026_10_26_000001_hash_organization_invitation_tokens.php'))->up();

    expect($invitation->fresh()->token)->toBe(hash('sha256', $plain))
        ->and(OrganizationInvitation::findPending($plain)?->is($invitation))->toBeTrue();
});
