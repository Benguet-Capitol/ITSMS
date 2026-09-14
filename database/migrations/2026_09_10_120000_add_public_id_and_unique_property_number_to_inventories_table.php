<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Sprint 3 exit-gate items that were planned but never actually
     * implemented: a DB-level unique index on `property_number` (today
     * only enforced client-side) and a `public_id` (ULID) column --
     * confirmed empty of duplicates/nulls first (4695 rows, 0 dupes) so
     * the unique index can be added directly, no cleanup migration
     * needed. `public_id` is the safe, non-guessable identifier future
     * external surfaces (Sprint 8's QR codes) will reference instead of
     * the raw sequential `id`.
     */
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->unique('property_number');
            $table->ulid('public_id')->nullable()->after('id');
        });

        // Backfill existing rows before enforcing NOT NULL/UNIQUE below --
        // a no-op on a fresh/empty table.
        DB::table('inventories')->select('id')->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('inventories')->where('id', $row->id)
                        ->update(['public_id' => (string) Str::ulid()]);
                }
            });

        // doctrine/dbal isn't installed, so Blueprint::change() isn't
        // available -- a raw MODIFY is the standard Laravel workaround,
        // and this app is MySQL-only (see phpunit.xml / DB_CONNECTION).
        DB::statement('ALTER TABLE inventories MODIFY public_id CHAR(26) NOT NULL');

        Schema::table('inventories', function (Blueprint $table) {
            $table->unique('public_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropUnique(['property_number']);
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
