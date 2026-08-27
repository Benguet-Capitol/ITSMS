<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Profile;

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
    protected $description = 'Mark users as offline if inactive for more than 2 minutes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $threshold = now()->subMinutes(2);

        $affected = Profile::query()
          ->whereIn('status', [
              Profile::STATUS_ONLINE,
              Profile::STATUS_BUSY,
          ])
          ->whereNotNull('last_seen_at')
          ->where('last_seen_at', '<', $threshold)
          ->update([
              'status' => Profile::STATUS_OFFLINE,
              'engagement' => null,
              'updated_at' => now(),
          ]);

        $this->info("Marked {$affected} inactive users offline.");

        return Command::SUCCESS;
    }
}
