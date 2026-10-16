<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves CCTV Footage requests onto the shared accept/assign workflow
 * (docs/adr/0002-request-workflow.md). The DPO recommendation and the
 * Provincial Administrator's approval are signed on paper before a
 * request is entered, so the old in-system recommend/approve/deny/release
 * chain (and its per-step timestamps) goes away:
 *
 *   released            -> completed (completed_at = released_at)
 *   recommended/approved -> pending (approved on paper; nobody linked yet)
 *   denied              -> cancelled, reason "Denied: <denial reason>"
 *
 * Also adds the requestor feedback fields (rating, name, date) so CCTV
 * requests complete like the other types.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cctv_footage_requests', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable()->after('status');
            $table->timestamp('finished_at')->nullable()->after('accepted_at');
            $table->timestamp('completed_at')->nullable()->after('finished_at');
            $table->timestamp('reopened_at')->nullable()->after('completed_at');
            $table->text('cancellation_reason')->nullable()->after('reopened_at');

            $table->unsignedTinyInteger('feedback_rating')->nullable();
            $table->string('feedback_name')->nullable();
            $table->date('feedback_date')->nullable();
        });

        DB::table('cctv_footage_requests')
            ->where('status', 'released')
            ->update(['status' => 'completed', 'completed_at' => DB::raw('released_at')]);

        DB::table('cctv_footage_requests')
            ->whereIn('status', ['recommended', 'approved'])
            ->update(['status' => 'pending']);

        DB::table('cctv_footage_requests')
            ->where('status', 'denied')
            ->update([
                'status' => 'cancelled',
                'cancellation_reason' => DB::raw("CONCAT('Denied: ', COALESCE(denial_reason, ''))"),
            ]);

        Schema::table('cctv_footage_requests', function (Blueprint $table) {
            $table->dropColumn([
                'recommended_at', 'approved_at', 'denied_at', 'released_at', 'cancelled_at', 'denial_reason',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('cctv_footage_requests', function (Blueprint $table) {
            $table->text('denial_reason')->nullable();
            $table->timestamp('recommended_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('denied_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
        });

        DB::table('cctv_footage_requests')
            ->where('status', 'completed')
            ->update(['status' => 'released', 'released_at' => DB::raw('completed_at')]);

        DB::table('cctv_footage_requests')
            ->where('status', 'cancelled')
            ->where('cancellation_reason', 'like', 'Denied: %')
            ->update([
                'status' => 'denied',
                'denial_reason' => DB::raw('SUBSTRING(cancellation_reason, 9)'),
            ]);

        DB::table('cctv_footage_requests')
            ->whereIn('status', ['in_progress', 'on_hold', 'for_completion'])
            ->update(['status' => 'pending']);

        Schema::table('cctv_footage_requests', function (Blueprint $table) {
            $table->dropColumn([
                'accepted_at', 'finished_at', 'completed_at', 'reopened_at', 'cancellation_reason',
                'feedback_rating', 'feedback_name', 'feedback_date',
            ]);
        });

        DB::table('request_personnel')->where('requestable_type', 'cctv_footage')->delete();
    }
};
