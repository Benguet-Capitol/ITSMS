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
        Schema::table('solutions', function (Blueprint $table) {
            $table->dropForeign(['author_id']);
        });

        // doctrine/dbal isn't installed, so column-modifying Blueprint::change()
        // isn't available here -- raw SQL instead.
        DB::statement('ALTER TABLE solutions MODIFY author_id BIGINT UNSIGNED NULL');

        Schema::table('solutions', function (Blueprint $table) {
            $table->foreign('author_id')->references('id')->on('profiles')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solutions', function (Blueprint $table) {
            $table->dropForeign(['author_id']);
        });

        DB::statement('ALTER TABLE solutions MODIFY author_id BIGINT UNSIGNED NOT NULL');

        Schema::table('solutions', function (Blueprint $table) {
            $table->foreign('author_id')->references('id')->on('profiles')->cascadeOnDelete();
        });
    }
};
