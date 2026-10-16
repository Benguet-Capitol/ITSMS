<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves Documentation requests onto the shared accept/assign workflow
 * (docs/adr/0002-request-workflow.md), as was done for Design. The
 * hand-typed "Assigned Personnel" and "Date Received" are replaced by
 * request_personnel and accepted_at; per the client, existing values are
 * not carried over. Requests that were in progress or on hold have nobody
 * linked, so they go back to pending.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentation_requests', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable()->after('status');
            $table->timestamp('finished_at')->nullable()->after('accepted_at');
            $table->timestamp('completed_at')->nullable()->after('finished_at');
            $table->timestamp('reopened_at')->nullable()->after('completed_at');
            $table->text('cancellation_reason')->nullable()->after('reopened_at');
        });

        DB::table('documentation_requests')
            ->whereIn('status', ['in_progress', 'on_hold'])
            ->update(['status' => 'pending']);

        Schema::table('documentation_requests', function (Blueprint $table) {
            $table->dropColumn(['assigned_personnel', 'date_received']);
        });
    }

    public function down(): void
    {
        Schema::table('documentation_requests', function (Blueprint $table) {
            $table->string('assigned_personnel')->nullable()->after('additional_notes');
            $table->date('date_received')->nullable()->after('assigned_personnel');
        });

        DB::table('documentation_requests')
            ->where('status', 'for_completion')
            ->update(['status' => 'in_progress']);

        Schema::table('documentation_requests', function (Blueprint $table) {
            $table->dropColumn(['accepted_at', 'finished_at', 'completed_at', 'reopened_at', 'cancellation_reason']);
        });

        DB::table('request_personnel')->where('requestable_type', 'documentation')->delete();
    }
};
