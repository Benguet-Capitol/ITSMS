<?php

namespace App\Console\Commands;

use App\Models\Profile;
use Illuminate\Console\Command;

class UsersMarkOffline extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:mark-offline';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark users idle after 2 minutes and offline after 15 minutes of inactivity';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $idleThreshold = now()->subMinutes(2);
        $offlineThreshold = now()->subMinutes(15);

        // engagement (ready/busy) is deliberately left untouched here -- see
        // ProfileController::markIdle()/setStatusOffline() for why.
        Profile::query()
            ->where('status', Profile::STATUS_ONLINE)
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '<', $idleThreshold)
            ->update([
                'status' => Profile::STATUS_IDLE,
            ]);

        Profile::query()
            ->whereIn('status', [
                Profile::STATUS_ONLINE,
                Profile::STATUS_IDLE,
            ])
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '<', $offlineThreshold)
            ->update([
                'status' => Profile::STATUS_OFFLINE,
            ]);

        return self::SUCCESS;
    }
}
