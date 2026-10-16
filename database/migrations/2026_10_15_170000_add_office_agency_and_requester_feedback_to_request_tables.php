<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Request forms, round two:
 *
 * - Department/Office is picked from the HRIS office list (office_id,
 *   office_code), like tickets; the existing text column keeps the office
 *   description for printing, searching and older records.
 * - All but IS Change and CCTV can mark the requester as from another
 *   agency (is_other_agency + agency_id), like tickets. CCTV has no
 *   department field at all.
 * - The requestor's feedback name is the requester's own name: existing
 *   ratings get it filled in (HasRequestWorkflow keeps it in step from now
 *   on).
 * - Other IT Service control numbers are server-assigned like the other
 *   types; requests without one get the next YYYY-NNNN of their year,
 *   oldest first. Numbers already typed in are kept.
 */
return new class extends Migration
{
    /** table => [department/office text column, requester column, allows other agency] */
    private const TABLES = [
        'design_requests' => ['department_office', 'requester_name', true],
        'documentation_requests' => ['department_office', 'requestor_name', true],
        'network_assistance_requests' => ['department_office', 'requestor_name', true],
        'dts_user_requests' => ['department', 'requested_by', true],
        'other_it_service_requests' => ['department_office', 'requestor_name', true],
        'is_change_requests' => ['department_office', 'requested_by', false],
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => [$officeColumn, , $allowsAgency]) {
            Schema::table($table, function (Blueprint $blueprint) use ($officeColumn, $allowsAgency) {
                $blueprint->string('office_id')->nullable()->after($officeColumn);
                $blueprint->string('office_code')->nullable()->after('office_id');

                if ($allowsAgency) {
                    $blueprint->boolean('is_other_agency')->default(false)->after('office_code');
                    $blueprint->foreignId('agency_id')->nullable()->after('is_other_agency')
                        ->constrained()->nullOnDelete();
                }
            });
        }

        $requesterColumns = [
            ...array_map(fn (array $config) => $config[1], self::TABLES),
            'cctv_footage_requests' => 'requester_name',
        ];

        foreach ($requesterColumns as $table => $requesterColumn) {
            DB::table($table)
                ->whereNotNull('feedback_rating')
                ->update(['feedback_name' => DB::raw($requesterColumn)]);
        }

        $this->numberOtherItServiceRequests();
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => [, , $allowsAgency]) {
            Schema::table($table, function (Blueprint $blueprint) use ($allowsAgency) {
                if ($allowsAgency) {
                    $blueprint->dropConstrainedForeignId('agency_id');
                    $blueprint->dropColumn('is_other_agency');
                }

                $blueprint->dropColumn(['office_id', 'office_code']);
            });
        }
        // Backfilled control numbers and feedback names are left in place:
        // they're valid values, and there's no record of which were blank.
    }

    /** Same YYYY-NNNN sequence as HasYearlyControlNumber, per year of creation. */
    private function numberOtherItServiceRequests(): void
    {
        $unnumbered = DB::table('other_it_service_requests')
            ->whereNull('control_number')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'created_at']);

        $lastByYear = [];

        foreach ($unnumbered as $row) {
            $year = Carbon::parse($row->created_at ?? now())->format('Y');

            $lastByYear[$year] ??= DB::table('other_it_service_requests')
                ->where('control_number', 'like', "{$year}-%")
                ->pluck('control_number')
                ->map(fn (string $number) => (int) substr($number, strlen($year) + 1))
                ->max() ?? 0;

            $lastByYear[$year]++;

            DB::table('other_it_service_requests')
                ->where('id', $row->id)
                ->update(['control_number' => sprintf('%s-%04d', $year, $lastByYear[$year])]);
        }
    }
};
