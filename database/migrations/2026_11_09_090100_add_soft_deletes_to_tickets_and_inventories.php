<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting a ticket or inventory item now hides it instead of removing
 * it, so it can be restored and its history stays intact. A deleted
 * item keeps its property number (and a ticket its ticket number): the
 * unique indexes still cover deleted rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['tickets', 'inventories'] as $name) {
            if (! Schema::hasColumn($name, 'deleted_at')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->softDeletes();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['tickets', 'inventories'] as $name) {
            if (Schema::hasColumn($name, 'deleted_at')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->dropSoftDeletes();
                });
            }
        }
    }
};
