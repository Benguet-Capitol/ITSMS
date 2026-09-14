<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable()->after('date');
            $table->timestamp('resolved_at')->nullable()->after('accepted_at');
            $table->string('released_by')->nullable()->after('released_at');
        });

        // doctrine/dbal isn't installed, so Blueprint::renameColumn() isn't
        // available here -- raw SQL instead, matching
        // 2026_09_08_150000_make_tickets_profile_and_it_service_nullable.php.
        // CHANGE COLUMN (not RENAME COLUMN) for compatibility with older
        // MariaDB versions that don't support the RENAME COLUMN syntax.
        DB::statement('ALTER TABLE tickets CHANGE priority complexity VARCHAR(255) NULL');

        // Existing rows predate the Simple/Moderate/Complex classification --
        // map their old free-text values across rather than leaving stale
        // low/medium/high strings in a column now treated as an enum.
        DB::table('tickets')->where('complexity', 'low')->update(['complexity' => 'simple']);
        DB::table('tickets')->where('complexity', 'medium')->update(['complexity' => 'moderate']);
        DB::table('tickets')->where('complexity', 'high')->update(['complexity' => 'complex']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('tickets')->where('complexity', 'simple')->update(['complexity' => 'low']);
        DB::table('tickets')->where('complexity', 'moderate')->update(['complexity' => 'medium']);
        DB::table('tickets')->where('complexity', 'complex')->update(['complexity' => 'high']);

        DB::statement('ALTER TABLE tickets CHANGE complexity priority VARCHAR(255) NULL');

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['accepted_at', 'resolved_at', 'released_by']);
        });
    }
};
