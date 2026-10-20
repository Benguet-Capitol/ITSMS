<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An assessment closes a ticket the same way a resolution does, so the
     * Complexity Check needs its end time too. assess() stamps assessed_at
     * from now on; existing assessed tickets are backfilled from their live
     * assessment's updated_at -- assess() is the only writer of that row
     * (a re-assessment after reopen restores and updates it), so updated_at
     * is when the ticket was last assessed. reopen() soft-deletes the
     * assessment, so trashed rows are skipped.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('tickets', 'assessed_at')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->timestamp('assessed_at')->nullable()->after('resolved_at');
            });
        }

        DB::table('tickets')
            ->join('ticket_assessments', 'ticket_assessments.ticket_id', '=', 'tickets.id')
            ->whereNull('ticket_assessments.deleted_at')
            ->whereNull('tickets.assessed_at')
            ->update(['tickets.assessed_at' => DB::raw('ticket_assessments.updated_at')]);
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('assessed_at');
        });
    }
};
