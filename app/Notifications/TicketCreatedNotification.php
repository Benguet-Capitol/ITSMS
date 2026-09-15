<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketCreatedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Ticket $ticket, public bool $isPriorityMatch = false)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * Every IT Technical user gets the in-app (database) notification for
     * general awareness, but only ones whose office/agency actually matches
     * the ticket get emailed -- otherwise every ticket emails the entire
     * technical team regardless of relevance.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $this->isPriorityMatch ? ['database', 'mail'] : ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'notification_type' => 'ticket_created',
            'ticket_id' => $this->ticket->id,
            'concern' => $this->ticket->concern,
            'agency' => $this->ticket->agency?->name,
            'is_priority_match' => $this->isPriorityMatch,
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New Ticket #{$this->ticket->id}")
            ->line("A new ticket has been created: {$this->ticket->concern}")
            ->when($this->isPriorityMatch, fn ($mail) => $mail->line('This ticket is in your assigned office/agency.'))
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
