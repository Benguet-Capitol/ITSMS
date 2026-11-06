<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The client's satisfaction rating for a ticket, the same 1-5 rating,
 * name and date the request types record (other_it_service_requests and
 * the rest). Entered with the ticket's Rate action once it's resolved or
 * assessed. The older quality/efficiency/timeliness columns were never
 * written by anything and are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedTinyInteger('feedback_rating')->nullable()->after('timeliness');
            $table->string('feedback_name')->nullable()->after('feedback_rating');
            $table->date('feedback_date')->nullable()->after('feedback_name');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['feedback_rating', 'feedback_name', 'feedback_date']);
        });
    }
};
