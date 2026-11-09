<?php

namespace App\Notifications;

use App\Models\Profile;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * An admin put the notified technician on a ticket -- assigned outright,
 * or transferred to them from someone else ($transferredFrom).
 */
class TicketAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public Profile $assignedBy,
        public ?Profile $transferredFrom = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'notification_type' => 'ticket_assigned',
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'concern' => $this->ticket->concern,
            'assigned_by_profile_id' => $this->assignedBy->id,
            'assigned_by_display_name' => $this->assignedBy->display_name,
            'transferred_from_display_name' => $this->transferredFrom?->display_name,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $from = $this->transferredFrom
            ? " (transferred from {$this->transferredFrom->display_name})"
            : '';

        return (new MailMessage)
            ->subject("Assigned: Ticket {$this->ticket->ticket_number}")
            ->line("{$this->assignedBy->display_name} assigned you to ticket {$this->ticket->ticket_number}{$from}.")
            ->line("Concern: {$this->ticket->concern}")
            ->action('View Ticket', rtrim((string) config('app.frontend_url'), '/')."/tickets/{$this->ticket->id}");
    }
}
