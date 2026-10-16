<?php

namespace App\Notifications;

use App\Models\Profile;
use App\Support\RequestTypes;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Someone else accepted a request the notified user is already on. */
class RequestPersonnelJoinedNotification extends Notification
{
    use Queueable;

    public function __construct(public Model $request, public Profile $joinedProfile) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        $type = RequestTypes::forModel($this->request);

        return [
            'notification_type' => 'request_personnel_joined',
            'request_type' => $this->request->getMorphClass(),
            'request_id' => $this->request->getKey(),
            'request_label' => $type['label'],
            'control_number' => $this->request->workflowReference(),
            'path' => $type['path'],
            'joined_profile_id' => $this->joinedProfile->id,
            'joined_display_name' => $this->joinedProfile->display_name,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $type = RequestTypes::forModel($this->request);

        return (new MailMessage)
            ->subject("Update on {$type['label']} {$this->request->workflowReference()}")
            ->line("{$this->joinedProfile->display_name} has accepted {$type['label']} {$this->request->workflowReference()}.")
            ->action('View Requests', url($type['path']));
    }
}
