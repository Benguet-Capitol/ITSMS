<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Request for Change or Modification of Information System. Unlike the
 * other request forms this follows a software-change lifecycle (review ->
 * development -> testing -> user-acceptance sign-off). The form requires
 * supporting documentation; ITSMS has no file storage, so what was attached
 * (on paper/email) is recorded in `attachments_description` instead of
 * uploading files.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('is_change_requests', function (Blueprint $table) {
            $table->id();

            $table->string('control_number')->unique();
            $table->string('status')->default('pending')->index();

            $table->date('date_of_request');
            $table->string('department_office');
            $table->string('information_system')->index();

            $table->string('request_type')->index();
            $table->string('request_type_other')->nullable();
            $table->text('modules_affected')->nullable();
            $table->text('details');
            $table->text('attachments_description');

            $table->string('priority')->index();
            $table->date('date_required')->index();

            $table->string('requested_by');

            $table->string('reviewed_by')->nullable();
            $table->date('reviewed_date')->nullable();

            $table->string('programmer_name')->nullable();
            $table->date('date_received')->nullable();

            $table->string('testing_schedule')->nullable();
            $table->string('deployed_by')->nullable();
            $table->string('approved_by')->nullable();
            $table->date('approved_date')->nullable();
            $table->text('remarks')->nullable();

            $table->unsignedTinyInteger('feedback_rating')->nullable();
            $table->string('feedback_name')->nullable();
            $table->date('feedback_date')->nullable();

            $table->string('accepted_by')->nullable();
            $table->date('accepted_date')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('is_change_requests');
    }
};
