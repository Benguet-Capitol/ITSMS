<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Request for Documentation (form PLGU-BENGUET|ICT-11): photo/video
 * documentation and live feed of a program or activity. Same shape as
 * other_it_service_requests (status, IT/MIS office use, feedback), with a
 * server-assigned YYYY-NNNN control number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentation_requests', function (Blueprint $table) {
            $table->id();

            $table->string('control_number')->unique();
            $table->string('status')->default('pending')->index();

            $table->date('date_of_request');
            $table->string('department_office');
            $table->string('requestor_name');

            $table->boolean('service_photo')->default(false);
            $table->boolean('service_video')->default(false);
            $table->boolean('service_live_feed')->default(false);

            $table->text('program_activity_details');
            $table->date('documentation_date')->index();
            $table->date('documentation_end_date')->nullable();
            $table->text('specific_shots')->nullable();
            $table->text('additional_notes')->nullable();

            $table->string('assigned_personnel')->nullable();
            $table->date('date_received')->nullable();
            $table->text('action_taken')->nullable();

            $table->unsignedTinyInteger('feedback_rating')->nullable();
            $table->string('feedback_name')->nullable();
            $table->date('feedback_date')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentation_requests');
    }
};
