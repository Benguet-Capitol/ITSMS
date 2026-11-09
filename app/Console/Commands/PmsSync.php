<?php

namespace App\Console\Commands;

use App\Models\PmsSyncRun;
use App\Services\PmsPropertySync;
use Illuminate\Console\Command;

/**
 * Copies PGSO's property list for the inventory counter-check
 * (docs/inventory-reconciliation.md). Scheduled nightly; safe to re-run.
 */
class PmsSync extends Command
{
    protected $signature = 'pms:sync';

    protected $description = 'Copy the PGSO property list (PMS) for the inventory counter-check';

    public function handle(PmsPropertySync $sync): int
    {
        if (! PmsPropertySync::isConfigured()) {
            $this->warn('PMS_BASE_URL and PMS_API_KEY are not set; nothing to sync.');

            return self::SUCCESS;
        }

        $this->line('Downloading the PMS property list (this takes about half a minute)...');

        $run = $sync->run();

        if (! $run) {
            $this->warn('Another sync is already running.');

            return self::SUCCESS;
        }

        if ($run->status !== PmsSyncRun::SUCCEEDED) {
            $this->error("Sync failed: {$run->error}");

            return self::FAILURE;
        }

        $this->info("Copied {$run->row_count} properties".($run->skipped_count ? ", skipped {$run->skipped_count} without an id or number" : '').'.');

        return self::SUCCESS;
    }
}
