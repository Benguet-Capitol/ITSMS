<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Ticket;
use App\Models\Profile;

class TicketPersonnelJoinedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Ticket $ticket, public Profile $joinedProfile)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'notification_type' => 'ticket_personnel_joined',
            'ticket_id' => $this->ticket->id,
            'joined_profile_id' => $this->joinedProfile->id,
            'joined_display_name' => $this->joinedProfile->display_name,
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Update on Ticket #{$this->ticket->id}")
            ->line("{$this->joinedProfile->display_name} has accepted ticket #{$this->ticket->id} — {$this->ticket->concern}.")
            ->action('View Ticket', url("/tickets/{$this->ticket->id}"));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
