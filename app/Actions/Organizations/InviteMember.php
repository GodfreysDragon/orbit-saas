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

class InviteMember
{
    /**
     * Create (or refresh) an invitation and email it on the queue.
     * Validation of the email and seat limits happens in the form request.
     */
    public function handle(Organization $organization, User $inviter, string $email, OrganizationRole $role): OrganizationInvitation
    {
        // A new token on every invite, so re-inviting invalidates the old link.
        $token = OrganizationInvitation::newToken();

        $invitation = $organization->invitations()->updateOrCreate(
            ['email' => Str::lower($email)],
            [
                'role' => $role,
                'invited_by_id' => $inviter->getKey(),
                'token' => OrganizationInvitation::hashToken($token),
                'expires_at' => now()->addDays(7),
            ],
        );
        $invitation->plainToken = $token;

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
    }
}
