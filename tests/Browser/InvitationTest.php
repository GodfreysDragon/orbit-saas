<?php

use App\Enums\OrganizationRole;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Support\Facades\Notification;

test('an owner invites a teammate who signs up from the link and joins', function () {
    Notification::fake();
    [$organization, $owner] = organizationWithUser();
    $organization->update(['name' => 'Acme Inc']);

    visit('/login')
        ->fill('email', $owner->email)
        ->fill('password', 'password')
        ->press('@login-button')
        ->assertPathIs(route('organization.dashboard', absolute: false))
        ->navigate(route('members.index', absolute: false))
        ->fill('#invite-email', 'new.hire@example.org')
        ->press('@send-invitation')
        ->assertSee('Invitation sent to new.hire@example.org.')
        ->click('@sidebar-menu-button')
        ->click('@logout-button')
        ->assertPathIs('/');

    $invitation = OrganizationInvitation::where('email', 'new.hire@example.org')->sole();

    expect($invitation->role)->toBe(OrganizationRole::Member);

    // Only the token's hash is stored; the link comes from the email.
    $token = null;
    Notification::assertSentOnDemand(
        OrganizationInvitationNotification::class,
        function (OrganizationInvitationNotification $notification) use (&$token) {
            $token = $notification->token;

            return true;
        },
    );

    visit(route('invitations.show', $token, absolute: false))
        ->assertSee('Acme Inc')
        ->click('@invitation-register')
        ->assertValue('#email', 'new.hire@example.org')
        ->fill('name', 'New Hire')
        ->fill('password', 'a-long-password')
        ->fill('password_confirmation', 'a-long-password')
        ->press('@register-user-button')
        ->assertPathIs(route('organization.dashboard', $organization, absolute: false))
        ->assertSee('Welcome to Acme Inc!')
        ->assertSeeIn('@organization-switcher', 'Acme Inc')
        ->assertNoJavaScriptErrors();

    $newHire = User::where('email', 'new.hire@example.org')->sole();

    expect($newHire->hasVerifiedEmail())->toBeTrue()
        ->and($newHire->roleIn($organization))->toBe(OrganizationRole::Member);
});
