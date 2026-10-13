<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Request for New DTS User / Update of User Details (PGO-IT/MIS form).
 *
 * The paper form has an "Initial Password" line; it is deliberately NOT a
 * column -- a working DTS password must not be stored in ITSMS. The
 * printout leaves the line blank for IT to fill in by hand. The paper form
 * also doesn't say whether it's a new user or an update, so
 * `request_type` (+ `update_details`) was added.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dts_user_requests', function (Blueprint $table) {
            $table->id();

            $table->string('control_number')->unique();
            $table->string('status')->default('pending')->index();

            $table->date('date_of_request');
            $table->string('request_type')->index();
            $table->text('update_details')->nullable();

            $table->string('full_name');
            $table->string('department');
            $table->string('position')->nullable();
            $table->string('dts_username', 50);

            $table->string('requested_by');

            $table->string('assigned_personnel')->nullable();
            $table->string('created_by')->nullable();
            $table->date('date_created')->nullable();

            $table->unsignedTinyInteger('feedback_rating')->nullable();
            $table->string('feedback_name')->nullable();
            $table->date('feedback_date')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dts_user_requests');
    }
};
