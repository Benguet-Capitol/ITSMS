<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The per-component "remarks" now serve as the assessment's findings, so
     * the old free-text `findings` column is dropped and `component_remarks`
     * is renamed to `component_findings`. This project's local MariaDB
     * (10.4) predates native RENAME COLUMN support and doctrine/dbal isn't
     * installed, so the rename is done as add-copy-drop instead of
     * Schema::renameColumn().
     *
     * Compliance note: `findings` was a required, non-nullable text column
     * -- dropping it discards any value already recorded there. Nothing is
     * lost in this project's own dev database (verified: 0 existing
     * ticket_assessments rows at the time this migration was written), but
     * whoever owns the production deploy should confirm/export any real
     * `findings` text there first, the same as the other schema-altering
     * migrations in this project (see the ticket_assignments drop and the
     * inventories/tickets unique-constraint migrations for the established
     * pattern of flagging this rather than assuming).
     */
    public function up(): void
    {
        Schema::table('ticket_assessments', function (Blueprint $table) {
            $table->json('component_findings')->nullable()->after('components');
        });

        DB::table('ticket_assessments')->update([
            'component_findings' => DB::raw('component_remarks'),
        ]);

        Schema::table('ticket_assessments', function (Blueprint $table) {
            $table->dropColumn(['findings', 'component_remarks']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * Restores the columns' shapes, not their data -- `findings` text
     * (dropped in up()) cannot be recovered and comes back empty.
     */
    public function down(): void
    {
        Schema::table('ticket_assessments', function (Blueprint $table) {
            $table->text('findings')->nullable();
            $table->json('component_remarks')->nullable()->after('components');
        });

        DB::table('ticket_assessments')->update([
            'component_remarks' => DB::raw('component_findings'),
        ]);

        Schema::table('ticket_assessments', function (Blueprint $table) {
            $table->dropColumn('component_findings');
        });
    }
};
