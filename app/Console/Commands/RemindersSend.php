<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\WorkReminderNotification;
use App\Services\WorkReminders;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

/**
 * The 8:00 AM weekday reminder (routes/console.php):
 *
 * - every user in an admin role (WorkReminders::ADMIN_ROLES) gets the
 *   tickets and requests nobody accepted within a working day;
 * - everyone on accepted work that is overdue or unchanged for a working
 *   day gets their own list.
 *
 * Nobody is sent an empty reminder. Safe to re-run: someone already
 * reminded today (of that kind) is skipped unless --force is given.
 */
class RemindersSend extends Command
{
    protected $signature = 'reminders:send
        {--dry-run : Only show who would be reminded of how many items}
        {--force : Send even to people already reminded today}';

    protected $description = 'Remind admins of unaccepted work and technicians of their overdue or stalled work';

    public function handle(): int
    {
        $reminders = new WorkReminders;

        $unaccepted = $reminders->unaccepted();
        $admins = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('title', WorkReminders::ADMIN_ROLES))
            ->with('profile')
            ->get();

        $sent = 0;

        if ($unaccepted->isEmpty()) {
            $this->info('Unaccepted: none past a working day.');
        } else {
            $this->info("Unaccepted: {$unaccepted->count()} item(s), for {$admins->count()} admin(s).");

            foreach ($admins as $admin) {
                $sent += $this->remind($admin, 'unaccepted', $unaccepted);
            }
        }

        $personal = $reminders->needingAttentionByProfile();
        $this->info("Open work needing attention: {$personal->count()} person(s).");

        foreach ($personal as $entry) {
            $user = $entry['profile']->user;

            if (! $user) {
                continue;
            }

            $sent += $this->remind($user, 'mine', $entry['items']);
        }

        $this->info($this->option('dry-run') ? 'Dry run: nothing sent.' : "Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }

    /** @return int 1 when sent, else 0 */
    private function remind(User $user, string $kind, Collection $items): int
    {
        $name = $user->profile?->display_name ?: $user->email;

        if (! $this->option('force') && $this->remindedToday($user, $kind)) {
            $this->line("  skip {$name}: already reminded today");

            return 0;
        }

        $this->line("  {$name}: {$items->count()} item(s)");

        if ($this->option('dry-run')) {
            return 0;
        }

        try {
            $user->notify(new WorkReminderNotification($kind, $items));

            return 1;
        } catch (Throwable $e) {
            // One bad mailbox mustn't stop everyone else's reminder.
            report($e);
            $this->error("  failed for {$name}: {$e->getMessage()}");

            return 0;
        }
    }

    private function remindedToday(User $user, string $kind): bool
    {
        return $user->notifications()
            ->where('type', WorkReminderNotification::class)
            ->where('data->kind', $kind)
            ->where('created_at', '>=', today())
            ->exists();
    }
}
