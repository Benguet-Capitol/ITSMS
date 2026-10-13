<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Request for CCTV Footage (form PLGU-BENGUET ICT-08). Requesters are
 * usually walk-ins, police, or court-order holders who aren't in HRIS, so
 * their details are free text. Supporting documents (valid ID, SPA, court
 * order, police blotter, ...) are recorded as a checklist only -- the
 * papers stay in the physical file so no sensitive scans are stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cctv_footage_requests', function (Blueprint $table) {
            $table->id();

            $table->string('control_number')->unique();
            $table->string('status')->default('pending')->index();

            $table->date('date_of_request');
            $table->string('requester_name');
            $table->string('requester_address')->nullable();
            $table->string('requester_contact_number', 50)->nullable();

            $table->date('date_of_incident');
            $table->time('footage_time_from')->nullable();
            $table->time('footage_time_to')->nullable();
            $table->string('camera_location');

            $table->string('requester_capacity');
            $table->string('requester_capacity_other')->nullable();

            $table->string('purpose');
            $table->json('reasons');
            $table->string('reason_others')->nullable();
            $table->text('description')->nullable();

            $table->json('documents_presented')->nullable();

            $table->timestamp('recommended_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('denied_at')->nullable();
            $table->text('denial_reason')->nullable();

            $table->dateTime('extracted_at')->nullable();
            $table->string('camera_name')->nullable();
            $table->string('nvr_number', 50)->nullable();
            $table->text('file_names')->nullable();
            $table->string('extracted_by')->nullable();

            $table->string('released_by')->nullable();
            $table->string('received_by')->nullable();
            $table->timestamp('released_at')->nullable();

            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cctv_footage_requests');
    }
};
