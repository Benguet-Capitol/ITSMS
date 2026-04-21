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
        Schema::create('brand_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')
              ->constrained()
              ->cascadeOnDelete();
            $table->foreignId('item_type_id')->nullable() // The general classification of this product model in your catalog.
              ->constrained()
              ->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('specification')->nullable();
            $table->json('specifications_json')->nullable();
            $table->string('part_number')->nullable();

            // might remove or delete later
            $table->string('status')->nullable();
            $table->string('image')->nullable();
            $table->string('year_released')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_models');
    }
};
