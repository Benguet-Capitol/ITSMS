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
        Schema::create('other_it_service_requests', function (Blueprint $table) {
            $table->id();

            $table->string('control_number')->unique()->nullable();

            $table->timestamp('date_of_request')->nullable();
            $table->string('department_office')->nullable();
            $table->string('requestor_name')->nullable();

            $table->boolean('service_printing')->default(false);
            $table->boolean('service_information_material')->default(false);
            $table->boolean('service_program_paper')->default(false);
            $table->boolean('service_brochure')->default(false);
            $table->boolean('service_iec_material')->default(false);
            $table->boolean('service_handbook')->default(false);
            $table->boolean('service_certificates')->default(false);
            $table->boolean('service_others')->default(false);
            $table->integer('service_qty')->nullable();
            $table->boolean('service_laptop_tv_setup')->default(false);
            $table->text('service_others_specify')->nullable();

            $table->text('program_activity_details')->nullable();
            $table->string('activity_date_text')->nullable();
            $table->string('activity_time')->nullable();

            $table->string('assigned_personnel')->nullable();
            $table->timestamp('date_received')->nullable();
            $table->text('action_taken')->nullable();

            $table->unsignedTinyInteger('feedback_rating')->nullable();
            $table->string('feedback_name')->nullable();
            $table->date('feedback_date')->nullable();
            $table->string('status')->default('pending');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('other_it_service_requests');
    }
};
