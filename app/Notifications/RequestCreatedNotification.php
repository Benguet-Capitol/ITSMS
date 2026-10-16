<?php

namespace App\Notifications;

use App\Support\RequestTypes;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

/**
 * New request of any workflow type -- sent to the same IT Technical users
 * as TicketCreatedNotification, in-app only (requests carry a free-text
 * office, so there's no office/agency match to justify an email).
 */
class RequestCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(public Model $request) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $type = RequestTypes::forModel($this->request);

        return [
            'notification_type' => 'request_created',
            'request_type' => $this->request->getMorphClass(),
            'request_id' => $this->request->getKey(),
            'request_label' => $type['label'],
            'control_number' => $this->request->workflowReference(),
            'path' => $type['path'],
        ];
    }
}
