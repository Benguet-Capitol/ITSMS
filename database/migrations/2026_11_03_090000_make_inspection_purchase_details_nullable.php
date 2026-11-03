<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An inspection is often called before the purchase details are known:
 * the end user, procurement mode, PO number and supplier are filled in
 * after the inspection (docs/requests-inspection.md), so they're optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_requests', function (Blueprint $table) {
            $table->string('end_user_office')->nullable()->change();
            $table->string('procurement_mode')->nullable()->change();
            $table->string('supplier')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Blank rows get an empty value so the columns can be NOT NULL again.
        DB::table('inspection_requests')->whereNull('end_user_office')->update(['end_user_office' => '']);
        DB::table('inspection_requests')->whereNull('procurement_mode')->update(['procurement_mode' => '']);
        DB::table('inspection_requests')->whereNull('supplier')->update(['supplier' => '']);

        Schema::table('inspection_requests', function (Blueprint $table) {
            $table->string('end_user_office')->nullable(false)->change();
            $table->string('procurement_mode')->nullable(false)->change();
            $table->string('supplier')->nullable(false)->change();
        });
    }
};
