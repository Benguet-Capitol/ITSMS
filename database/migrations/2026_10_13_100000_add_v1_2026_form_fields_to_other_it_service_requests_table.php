<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Brings the table in line with form PLGU-BENGUET|ICT-09-V1|2026:
 *
 * - "Ledwall and Sound System at EOC" is a new service checkbox.
 * - "Venue" is a new activity field.
 * - The standalone "Others: (Please Specify)" line now has its own
 *   checkbox, separate from the "Others" item inside the Printing list.
 *   `service_others` stays the Printing item; `service_other_service`
 *   owns `service_others_specify`. Existing rows that already have specify
 *   text are back-filled as other-service requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('other_it_service_requests', function (Blueprint $table) {
            $table->boolean('service_ledwall_eoc')->default(false)->after('service_laptop_tv_setup');
            $table->boolean('service_other_service')->default(false)->after('service_ledwall_eoc');
            $table->string('activity_venue')->nullable()->after('activity_time');
        });

        DB::table('other_it_service_requests')
            ->whereNotNull('service_others_specify')
            ->where('service_others_specify', '<>', '')
            ->update(['service_other_service' => true]);
    }

    public function down(): void
    {
        Schema::table('other_it_service_requests', function (Blueprint $table) {
            $table->dropColumn(['service_ledwall_eoc', 'service_other_service', 'activity_venue']);
        });
    }
};
