<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ticket_assessments', function (Blueprint $table) {
            $table->string('control_number')->nullable()->unique()->after('ticket_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_assessments', function (Blueprint $table) {
            $table->dropColumn('control_number');
        });
    }
};
