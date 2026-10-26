<?php

namespace App\Notifications;

use App\Models\OrganizationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The plain token travels with the notification because the database
     * only stores its hash.
     */
    public function __construct(public OrganizationInvitation $invitation, public string $token)
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organization = $this->invitation->organization;
        $inviter = $this->invitation->inviter->name ?? 'A teammate';

        return (new MailMessage)
            ->subject("You've been invited to join {$organization->name}")
            ->greeting('Hello!')
            ->line("{$inviter} has invited you to join **{$organization->name}** as {$this->invitation->role->label()}.")
            ->action('View invitation', route('invitations.show', $this->token))
            ->line("This invitation expires {$this->invitation->expires_at->diffForHumans()}.")
            ->line('If you were not expecting this invitation, you can ignore this email.');
    }
}
