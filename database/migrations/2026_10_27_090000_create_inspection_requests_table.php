<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inspection of delivered IT purchases, replacing the "IT Inspection Logs"
 * sheet (docs/requests-inspection.md). One request per purchase, with the
 * delivered items as a JSON list of {description, brand, quantity,
 * unit_cost, result, remarks}; each item's result (passed/partial/failed) is filled
 * in once inspected. Uses the shared request workflow (ADR 0002) without
 * the requestor's rating, so there are no feedback columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_requests', function (Blueprint $table) {
            $table->id();

            $table->string('control_number')->unique();
            $table->string('status')->default('pending')->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->date('date_of_request');
            $table->string('end_user_office');
            $table->string('office_id')->nullable();
            $table->string('office_code')->nullable();

            $table->string('procurement_mode');
            $table->string('po_number')->nullable()->index();
            $table->string('supplier');

            $table->json('items');

            $table->date('inspection_date')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        DB::table('request_personnel')->where('requestable_type', 'inspection')->delete();

        Schema::dropIfExists('inspection_requests');
    }
};
