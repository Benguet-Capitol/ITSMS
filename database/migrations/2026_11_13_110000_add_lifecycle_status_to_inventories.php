<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An inventory item's lifecycle (docs/inventory-status.md): active,
 * returned to PGSO, disposed or donated. The `status` column existed but
 * nothing could set it, so every item is "active" (or empty); this keeps
 * "disposed" where an old import wrote it and makes everything else active.
 *
 * Adds when the status last changed and a note (who received a donation,
 * the disposal document's number). Safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            if (! Schema::hasColumn('inventories', 'status_changed_at')) {
                $table->date('status_changed_at')->nullable()->after('status');
            }

            if (! Schema::hasColumn('inventories', 'status_note')) {
                $table->string('status_note', 500)->nullable()->after('status_changed_at');
            }
        });

        DB::table('inventories')->whereRaw('LOWER(TRIM(status)) = ?', ['disposed'])->update(['status' => 'disposed']);
        DB::table('inventories')
            ->where(fn ($q) => $q->whereNull('status')->orWhereNotIn('status', ['active', 'returned', 'disposed', 'donated']))
            ->update(['status' => 'active']);

        Schema::table('inventories', function (Blueprint $table) {
            $table->string('status')->default('active')->nullable(false)
                ->comment('active, returned (to PGSO), disposed, donated')
                ->change();

            if (! Schema::hasIndex('inventories', ['status'])) {
                $table->index('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->string('status')->nullable()->default(null)
                ->comment('Deployed, In Storage, Repair, Disposed')
                ->change();
            $table->dropColumn(['status_changed_at', 'status_note']);
        });
    }
};
