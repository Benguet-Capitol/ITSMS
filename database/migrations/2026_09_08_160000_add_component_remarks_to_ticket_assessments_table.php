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
            $table->json('component_remarks')->nullable()->after('components');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_assessments', function (Blueprint $table) {
            $table->dropColumn('component_remarks');
        });
    }
};
