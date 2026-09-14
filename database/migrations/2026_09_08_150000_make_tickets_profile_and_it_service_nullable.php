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
            $table->dropForeign(['profile_id']);
            $table->dropForeign(['it_service_id']);
        });

        // doctrine/dbal isn't installed, so column-modifying Blueprint::change()
        // isn't available here -- raw SQL instead.
        DB::statement('ALTER TABLE tickets MODIFY profile_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE tickets MODIFY it_service_id BIGINT UNSIGNED NULL');

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreign('profile_id')->references('id')->on('profiles')->nullOnDelete();
            $table->foreign('it_service_id')->references('id')->on('it_services')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['profile_id']);
            $table->dropForeign(['it_service_id']);
        });

        DB::statement('ALTER TABLE tickets MODIFY profile_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE tickets MODIFY it_service_id BIGINT UNSIGNED NOT NULL');

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreign('profile_id')->references('id')->on('profiles')->cascadeOnDelete();
            $table->foreign('it_service_id')->references('id')->on('it_services')->cascadeOnDelete();
        });
    }
};
