<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Request for Design/Lay-out/Editing (form PLGU-BENGUET|ICT-10). Unlike the
 * other request forms, each requested item (booklet, tarpaulin, video
 * editing, ...) carries its own dimension/resolution and quantity/length,
 * so they're stored as a JSON list of {item, dimension, quantity, specify}.
 * The IT/MIS section also records the release (date released, received
 * by, remarks).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_requests', function (Blueprint $table) {
            $table->id();

            $table->string('control_number')->unique();
            $table->string('status')->default('pending')->index();

            $table->date('date_of_request');
            $table->string('department_office');
            $table->date('date_needed')->index();

            $table->json('items');
            $table->json('file_formats')->nullable();
            $table->string('file_format_other')->nullable();
            $table->json('media_provided')->nullable();

            $table->string('requester_name');
            $table->string('requester_position')->nullable();
            $table->string('requester_email')->nullable();

            $table->string('assigned_personnel')->nullable();
            $table->date('date_received')->nullable();
            $table->date('date_released')->nullable();
            $table->string('received_by')->nullable();
            $table->text('remarks')->nullable();

            $table->unsignedTinyInteger('feedback_rating')->nullable();
            $table->string('feedback_name')->nullable();
            $table->date('feedback_date')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_requests');
    }
};
