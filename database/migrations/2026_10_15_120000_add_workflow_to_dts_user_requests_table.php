<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves DTS User requests onto the shared accept/assign workflow
 * (docs/adr/0002-request-workflow.md), as was done for Design. The
 * hand-typed "Assigned Personnel" is replaced by request_personnel; per
 * the client, existing values are not carried over. "Created by" and "Date
 * Created" (who set up the DTS account, and when) are the work's output,
 * not intake, so they stay. Requests that were in progress or on hold have
 * nobody linked, so they go back to pending.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dts_user_requests', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable()->after('status');
            $table->timestamp('finished_at')->nullable()->after('accepted_at');
            $table->timestamp('completed_at')->nullable()->after('finished_at');
            $table->timestamp('reopened_at')->nullable()->after('completed_at');
            $table->text('cancellation_reason')->nullable()->after('reopened_at');
        });

        DB::table('dts_user_requests')
            ->whereIn('status', ['in_progress', 'on_hold'])
            ->update(['status' => 'pending']);

        Schema::table('dts_user_requests', function (Blueprint $table) {
            $table->dropColumn('assigned_personnel');
        });
    }

    public function down(): void
    {
        Schema::table('dts_user_requests', function (Blueprint $table) {
            $table->string('assigned_personnel')->nullable()->after('requested_by');
        });

        DB::table('dts_user_requests')
            ->where('status', 'for_completion')
            ->update(['status' => 'in_progress']);

        Schema::table('dts_user_requests', function (Blueprint $table) {
            $table->dropColumn(['accepted_at', 'finished_at', 'completed_at', 'reopened_at', 'cancellation_reason']);
        });

        DB::table('request_personnel')->where('requestable_type', 'dts_user')->delete();
    }
};
