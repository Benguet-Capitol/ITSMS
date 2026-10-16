<?php

namespace App\Notifications;

use App\Models\Profile;
use App\Support\RequestTypes;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** An admin put the notified user on a request. */
class RequestAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(public Model $request, public Profile $assignedBy) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        $type = RequestTypes::forModel($this->request);

        return [
            'notification_type' => 'request_assigned',
            'request_type' => $this->request->getMorphClass(),
            'request_id' => $this->request->getKey(),
            'request_label' => $type['label'],
            'control_number' => $this->request->workflowReference(),
            'path' => $type['path'],
            'assigned_by_profile_id' => $this->assignedBy->id,
            'assigned_by_display_name' => $this->assignedBy->display_name,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $type = RequestTypes::forModel($this->request);

        return (new MailMessage)
            ->subject("Assigned: {$type['label']} {$this->request->workflowReference()}")
            ->line("{$this->assignedBy->display_name} assigned you to {$type['label']} {$this->request->workflowReference()}.")
            ->action('View Requests', url($type['path']));
    }
}
