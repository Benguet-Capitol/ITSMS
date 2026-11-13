<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * The 8:00 AM reminder (reminders:send), in two kinds:
 *
 * - `unaccepted`, to admins: tickets and requests nobody has accepted
 *   after a working day.
 * - `mine`, to a technician: their accepted work that is overdue or
 *   hasn't changed for a working day.
 *
 * The items are kept in the notification, so opening it shows the list
 * as it was at 8:00 AM.
 */
class WorkReminderNotification extends Notification
{
    use Queueable;

    /** Items kept in the in-app notification; the count is always the full one. */
    public const MAX_STORED_ITEMS = 50;

    /** Items written into the email before "and N more". */
    public const MAX_MAILED_ITEMS = 15;

    /**
     * @param  'unaccepted'|'mine'  $kind
     * @param  Collection<int, array<string, mixed>>  $items  WorkReminders items
     */
    public function __construct(public string $kind, public Collection $items) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'notification_type' => 'work_reminder',
            'kind' => $this->kind,
            'count' => $this->items->count(),
            'tickets' => $this->items->where('kind', 'ticket')->count(),
            'requests' => $this->items->where('kind', 'request')->count(),
            'items' => $this->items->take(self::MAX_STORED_ITEMS)->values()->all(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $count = $this->items->count();

        $mail = (new MailMessage)
            ->subject($this->subject())
            ->line(match (true) {
                $this->kind === 'unaccepted' && $count === 1 => 'Nobody has accepted this one after a working day. Please assign it or ask the team to accept it:',
                $this->kind === 'unaccepted' => "Nobody has accepted these {$count} after a working day. Please assign them or ask the team to accept them:",
                $count === 1 => "This one of yours is overdue or hasn't changed for a working day. Please update, finish or hand it over:",
                default => "These {$count} of yours are overdue or haven't changed for a working day. Please update, finish or hand them over:",
            });

        foreach ($this->items->take(self::MAX_MAILED_ITEMS) as $item) {
            $mail->line($this->itemLine($item));
        }

        if ($count > self::MAX_MAILED_ITEMS) {
            $mail->line('…and '.($count - self::MAX_MAILED_ITEMS).' more.');
        }

        return $mail->action('Open the dashboard', "{$frontend}/");
    }

    public function subject(): string
    {
        $count = $this->items->count();
        $noun = $count === 1 ? 'item' : 'items';

        return $this->kind === 'unaccepted'
            ? "ITSMS: {$count} {$noun} waiting to be accepted"
            : "ITSMS: {$count} of your items ".($count === 1 ? 'needs' : 'need').' attention';
    }

    private function itemLine(array $item): string
    {
        $reason = match ($item['reason']) {
            'overdue' => 'overdue',
            'no_update' => 'no change since '.self::date($item['since']),
            'unaccepted' => 'filed '.self::date($item['since']),
            default => null,
        };

        return collect([
            "{$item['type_label']} {$item['reference']}",
            $item['summary'] ?: null,
            $item['status'],
            $reason,
        ])->filter()->implode(' · ');
    }

    private static function date(?string $iso): string
    {
        return $iso ? Carbon::parse($iso)->timezone(config('app.timezone'))->format('M j, Y g:i A') : '';
    }
}
