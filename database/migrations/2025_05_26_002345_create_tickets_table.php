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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')
              ->constrained()
              ->cascadeOnDelete();
            $table->foreignId('inventory_id')->nullable()
              ->constrained()
              ->nullOnDelete();
            $table->foreignId('agency_id')->nullable()
              ->constrained()
              ->nullOnDelete();
            $table->foreignId('it_service_id')
              ->constrained()
              ->cascadeOnDelete();
            $table->foreignId('item_type_id')->nullable()
              ->constrained()
              ->nullOnDelete();
            $table->foreignId('solution_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ticket_number')->unique();
            $table->string('full_name')->nullable();
            $table->text('concern');
            $table->string('query_status')->nullable();
            $table->string('request_status')->nullable();
            $table->enum('service_method', ['on_site', 'pulled_out'])->nullable();
            $table->string('priority')->nullable();
            $table->timestamp('date')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('contact_number')->nullable();
            $table->boolean('is_other_agency')->default(false);

            // Ratings
            $table->tinyInteger('quality')->nullable()->comment('1-5 rating for quality');
            $table->tinyInteger('efficiency')->nullable()->comment('1-5 rating for efficiency');
            $table->tinyInteger('timeliness')->nullable()->comment('1-5 rating for timeliness');
            // $table->tinyInteger('rating')->nullable()->comment('Average rating (1-5, rounded)');


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
