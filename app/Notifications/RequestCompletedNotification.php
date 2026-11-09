<?php

namespace App\Notifications;

use App\Models\Profile;
use App\Support\RequestTypes;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to whoever filed the request when an admin completes it, so they
 * can tell the client -- clients come from HRIS and have no account or
 * email in ITSMS.
 */
class RequestCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(public Model $request, public Profile $completedBy) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        $type = RequestTypes::forModel($this->request);

        return [
            'notification_type' => 'request_completed',
            'request_type' => $this->request->getMorphClass(),
            'request_id' => $this->request->getKey(),
            'request_label' => $type['label'],
            'control_number' => $this->request->workflowReference(),
            'path' => $type['path'],
            'client_name' => $this->request->requesterName(),
            'completed_by_display_name' => $this->completedBy->display_name,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $type = RequestTypes::forModel($this->request);
        $reference = "{$type['label']} {$this->request->workflowReference()}";
        $client = $this->request->requesterName();

        return (new MailMessage)
            ->subject("Completed: {$reference}")
            ->line("{$this->completedBy->display_name} completed {$reference}, which you filed.")
            ->line($client ? "Please let the client, {$client}, know." : 'Please let the client know.')
            ->action('View Request', rtrim((string) config('app.frontend_url'), '/')."{$type['path']}/{$this->request->getKey()}");
    }
}
