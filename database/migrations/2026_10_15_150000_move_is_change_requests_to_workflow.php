<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves IS Change requests onto the shared accept/assign workflow
 * (docs/adr/0002-request-workflow.md). The development steps that used to
 * be statuses (reviewed / in_development / testing) become a separate
 * `stage`, changeable in any order while the request is in progress. The
 * programmer's hand-typed "Date received request" is replaced by
 * accepted_at; per the client it is not carried over. Requests that were
 * mid-development have nobody linked yet, so they go back to pending but
 * keep their step.
 */
return new class extends Migration
{
    private const STAGES = ['reviewed', 'in_development', 'testing'];

    public function up(): void
    {
        Schema::table('is_change_requests', function (Blueprint $table) {
            $table->string('stage')->nullable()->after('status');
            $table->timestamp('accepted_at')->nullable()->after('stage');
            $table->timestamp('finished_at')->nullable()->after('accepted_at');
            $table->timestamp('completed_at')->nullable()->after('finished_at');
            $table->timestamp('reopened_at')->nullable()->after('completed_at');
            $table->text('cancellation_reason')->nullable()->after('reopened_at');
        });

        DB::table('is_change_requests')
            ->whereIn('status', self::STAGES)
            ->update(['stage' => DB::raw('status'), 'status' => 'pending']);

        DB::table('is_change_requests')
            ->where('status', 'on_hold')
            ->update(['status' => 'pending']);

        Schema::table('is_change_requests', function (Blueprint $table) {
            $table->dropColumn('date_received');
        });
    }

    public function down(): void
    {
        Schema::table('is_change_requests', function (Blueprint $table) {
            $table->date('date_received')->nullable()->after('programmer_name');
        });

        // Unfinished work goes back to its development step as the status.
        DB::table('is_change_requests')
            ->whereIn('status', ['pending', 'in_progress', 'on_hold', 'for_completion'])
            ->whereIn('stage', self::STAGES)
            ->update(['status' => DB::raw('stage')]);

        DB::table('is_change_requests')
            ->whereIn('status', ['in_progress', 'for_completion'])
            ->update(['status' => 'pending']);

        Schema::table('is_change_requests', function (Blueprint $table) {
            $table->dropColumn(['stage', 'accepted_at', 'finished_at', 'completed_at', 'reopened_at', 'cancellation_reason']);
        });

        DB::table('request_personnel')->where('requestable_type', 'is_change')->delete();
    }
};
