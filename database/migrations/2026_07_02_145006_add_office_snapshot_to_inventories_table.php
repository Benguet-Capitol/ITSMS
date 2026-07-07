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
        Schema::table('inventories', function (Blueprint $table) {
            $table->unsignedBigInteger('office_id')->nullable()->after('employee_id');
            $table->string('office_code')->nullable()->after('office_id');
            $table->string('office_name')->nullable()->after('office_code');

            $table->index('office_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropIndex(['office_id']);
            $table->dropColumn(['office_id', 'office_code', 'office_name']);
        });
    }
};
