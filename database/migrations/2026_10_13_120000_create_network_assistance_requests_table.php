<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Request for Network Assistance -- Office Activities/Meetings (form
 * PLGU-BENGUET|ICT-15): Wi-Fi, ethernet wiring, network planning or other
 * network support for an activity. Same shape as documentation_requests
 * (status, IT/MIS office use, feedback), with a server-assigned YYYY-NNNN
 * control number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_assistance_requests', function (Blueprint $table) {
            $table->id();

            $table->string('control_number')->unique();
            $table->string('status')->default('pending')->index();

            $table->date('date_of_request');
            $table->string('department_office');
            $table->string('requestor_name');

            $table->boolean('service_wifi_installation')->default(false);
            $table->boolean('service_ethernet_wiring')->default(false);
            $table->boolean('service_network_planning')->default(false);
            $table->boolean('service_other_support')->default(false);
            $table->string('service_other_specify')->nullable();

            $table->text('program_activity_details');
            $table->date('activity_date')->index();
            $table->date('activity_end_date')->nullable();
            $table->time('activity_time')->nullable();
            $table->string('venue');

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
        Schema::dropIfExists('network_assistance_requests');
    }
};
