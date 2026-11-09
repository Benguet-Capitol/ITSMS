<?php

namespace App\Notifications;

use App\Models\Profile;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to whoever filed the ticket when it is resolved or assessed, so
 * they can tell the client -- clients come from HRIS and have no account
 * or email in ITSMS.
 */
class TicketCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(public Ticket $ticket, public Profile $completedBy) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'notification_type' => 'ticket_completed',
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'concern' => $this->ticket->concern,
            'status' => $this->ticket->query_status->value,
            'client_name' => $this->clientName(),
            'completed_by_display_name' => $this->completedBy->display_name,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $client = $this->clientName();
        $status = $this->ticket->query_status->value;

        return (new MailMessage)
            ->subject("Completed: Ticket {$this->ticket->ticket_number}")
            ->line("{$this->completedBy->display_name} {$status} ticket {$this->ticket->ticket_number}, which you filed.")
            ->line("Concern: {$this->ticket->concern}")
            ->line($client ? "Please let the client, {$client}, know." : 'Please let the client know.')
            ->action('View Ticket', rtrim((string) config('app.frontend_url'), '/')."/tickets/{$this->ticket->id}");
    }

    private function clientName(): ?string
    {
        return $this->ticket->client_name ?: $this->ticket->full_name ?: null;
    }
}
