<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The audit trail (docs/activity-log.md): one row per change to a
 * ticket, inventory item, request, user or role, plus logins and report
 * downloads. Rows are only ever inserted -- ActivityLog refuses updates
 * and deletes, and there is no route that changes them.
 *
 * loggable_id is a string because users have UUID keys and everything
 * else has integers. user_id is the actor (null for a failed login);
 * nullOnDelete so removing a user keeps the history of what they did.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            // The actor's name at the time, so the row still says who did
            // it after their account is removed.
            $table->string('user_name')->nullable();
            $table->string('action', 64);
            $table->string('loggable_type')->nullable();
            $table->string('loggable_id', 36)->nullable();
            // A readable name for the record at the time (ticket number,
            // property number, username...), kept even if it's renamed.
            $table->string('subject_label')->nullable();
            $table->json('changes')->nullable();
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['loggable_type', 'loggable_id']);
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
