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
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->dropForeign(['profile_id']);
        });

        // doctrine/dbal isn't installed, so column-modifying Blueprint::change()
        // isn't available here -- raw SQL instead.
        DB::statement('ALTER TABLE ticket_comments MODIFY profile_id BIGINT UNSIGNED NULL');

        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->foreign('profile_id')->references('id')->on('profiles')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->dropForeign(['profile_id']);
        });

        DB::statement('ALTER TABLE ticket_comments MODIFY profile_id BIGINT UNSIGNED NOT NULL');

        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->foreign('profile_id')->references('id')->on('profiles')->cascadeOnDelete();
        });
    }
};
