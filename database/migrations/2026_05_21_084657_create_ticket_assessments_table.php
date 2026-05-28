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
        Schema::create('ticket_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->text('findings');
            $table->text('recommendations');
            $table->boolean('replacement_available')->default(false);
            $table->text('specifications')->nullable();
            $table->json('components')->nullable();
            $table->string('reviewed_by');
            $table->string('reviewed_by_position')->nullable();
            $table->string('assessed_by');
            $table->string('assessed_by_position')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_assessments');
    }
};
