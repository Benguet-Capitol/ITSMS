<?php

use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    /** @var ClosureCommand $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('users:mark-offline')->everyFiveMinutes()->withoutOverlapping();

// Copy of PGSO's property list for the inventory counter-check; does
// nothing until PMS_BASE_URL is set.
Schedule::command('pms:sync')->dailyAt('02:00')->withoutOverlapping();
