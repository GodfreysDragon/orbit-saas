<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationRole;
use App\Models\Activity;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteMember
{
    /**
     * Create (or refresh) an invitation and email it on the queue. The form
     * request validates the email; the seat limit is checked here, under the
     * organization's lock, so two invitations can't take the last seat.
     *
     * @throws ValidationException when the plan has no free seat
     */
    public function handle(Organization $organization, User $inviter, string $email, OrganizationRole $role): OrganizationInvitation
    {
        $email = Str::lower($email);

        return $organization->withLock(function () use ($organization, $inviter, $email, $role): OrganizationInvitation {
            // Re-inviting a pending address reuses its seat.
            $alreadyInvited = $organization->invitations()->where('email', $email)->exists();

            if (! $alreadyInvited && ! $organization->canAddMembers()) {
                throw ValidationException::withMessages([
                    'email' => "You've used all seats on the {$organization->plan()->name} plan. Upgrade to invite more members.",
                ]);
            }

            // A new token on every invite, so re-inviting invalidates the old link.
            $token = OrganizationInvitation::newToken();

            $invitation = $organization->invitations()->updateOrCreate(
                ['email' => $email],
                [
                    'role' => $role,
                    'invited_by_id' => $inviter->getKey(),
                    'token' => OrganizationInvitation::hashToken($token),
                    'expires_at' => now()->addDays(7),
                ],
            );
            $invitation->plainToken = $token;

            // Queued after the transaction commits (see the notification).
            Notification::route('mail', $invitation->email)
                ->notify(new OrganizationInvitationNotification($invitation, $token));

            Activity::record(
                'member.invited',
                "Invited {$invitation->email} as {$role->label()}",
                $invitation,
                ['email' => $invitation->email, 'role' => $role->value],
                $organization,
                $inviter,
            );

            return $invitation;
        });
    }
}
