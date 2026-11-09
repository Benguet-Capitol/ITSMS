<?php

namespace App\Jobs;

use App\Models\PmsSyncRun;
use App\Services\PmsPropertySync;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * "Sync now" on the reconciliation page. The controller records the run
 * as started and dispatches this after the response, so the request
 * returns at once and the page follows the run instead of waiting half a
 * minute.
 */
class SyncPmsProperties
{
    use Dispatchable;

    public function __construct(public PmsSyncRun $run) {}

    public function handle(PmsPropertySync $sync): void
    {
        $sync->execute($this->run);
    }
}
