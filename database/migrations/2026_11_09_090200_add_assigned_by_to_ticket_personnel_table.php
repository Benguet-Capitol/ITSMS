<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who put a technician on a ticket: null when they accepted it
 * themselves, the admin's profile when it was assigned or transferred to
 * them -- the same column request_personnel has.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_personnel', function (Blueprint $table) {
            $table->foreignId('assigned_by_profile_id')
                ->nullable()
                ->after('profile_id')
                ->constrained('profiles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_personnel', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_by_profile_id');
        });
    }
};
