<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Sprint 4 exit-gate item, mirroring the Sprint 3 `inventories.public_id`
     * pattern: the safe, non-guessable identifier for future external
     * surfaces to reference instead of the raw sequential `id`.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->ulid('public_id')->nullable()->after('id');
        });

        DB::table('tickets')->select('id')->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('tickets')->where('id', $row->id)
                        ->update(['public_id' => (string) Str::ulid()]);
                }
            });

        // doctrine/dbal isn't installed, so Blueprint::change() isn't
        // available -- a raw MODIFY is the standard Laravel workaround,
        // and this app is MySQL-only (see phpunit.xml / DB_CONNECTION).
        DB::statement('ALTER TABLE tickets MODIFY public_id CHAR(26) NOT NULL');

        Schema::table('tickets', function (Blueprint $table) {
            $table->unique('public_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
