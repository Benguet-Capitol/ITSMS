<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a request carry several dates -- "October 2 to 4" and/or
 * "October 2 and 5" -- as a JSON `schedule` list (see App\Support\DateSchedule).
 *
 * Existing rows are backfilled from their single date / date range. The old
 * columns stay and are kept in step by the models (earliest date, latest
 * date) for sorting and indexes. Other IT Services' free-text
 * `activity_date_text` can't be parsed reliably, so those rows keep their
 * text and start with an empty schedule.
 */
return new class extends Migration
{
    /** table => [start column, end column|null] */
    private const BACKFILL = [
        'documentation_requests' => ['documentation_date', 'documentation_end_date'],
        'network_assistance_requests' => ['activity_date', 'activity_end_date'],
        'cctv_footage_requests' => ['date_of_incident', null],
    ];

    public function up(): void
    {
        foreach ([...array_keys(self::BACKFILL), 'other_it_service_requests'] as $table) {
            if (! Schema::hasColumn($table, 'schedule')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->json('schedule')->nullable();
                });
            }
        }

        foreach (self::BACKFILL as $table => [$startColumn, $endColumn]) {
            DB::table($table)
                ->whereNull('schedule')
                ->whereNotNull($startColumn)
                ->orderBy('id')
                ->each(function (object $row) use ($table, $startColumn, $endColumn) {
                    $from = substr((string) $row->{$startColumn}, 0, 10);
                    $to = $endColumn && $row->{$endColumn} ? substr((string) $row->{$endColumn}, 0, 10) : null;

                    DB::table($table)->where('id', $row->id)->update([
                        'schedule' => json_encode([['from' => $from, 'to' => $to === $from ? null : $to]]),
                    ]);
                });
        }
    }

    public function down(): void
    {
        foreach ([...array_keys(self::BACKFILL), 'other_it_service_requests'] as $table) {
            if (Schema::hasColumn($table, 'schedule')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('schedule');
                });
            }
        }
    }
};
