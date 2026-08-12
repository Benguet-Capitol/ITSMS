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
            $table->decimal('acquisition_cost', 12, 2)->nullable()->after('specifications');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_assessments', function (Blueprint $table) {
            $table->dropColumn('acquisition_cost');
        });
    }
};
