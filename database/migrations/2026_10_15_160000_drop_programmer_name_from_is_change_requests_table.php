<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The form's "Action by (Programmer)" is whoever works the request, so it
 * now comes from request_personnel like "Assigned to" on the other request
 * types (docs/adr/0002-request-workflow.md). Per the client, the typed
 * names are not carried over.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('is_change_requests', function (Blueprint $table) {
            $table->dropColumn('programmer_name');
        });
    }

    public function down(): void
    {
        Schema::table('is_change_requests', function (Blueprint $table) {
            $table->string('programmer_name')->nullable()->after('reviewed_date');
        });
    }
};
