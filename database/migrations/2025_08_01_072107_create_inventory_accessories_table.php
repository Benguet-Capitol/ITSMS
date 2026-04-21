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
        Schema::create('inventory_accessories', function (Blueprint $table) {
            // Peripherals that are used with a Primary Asset but don't have their own tag (like a generic mouse)
            // Mapping Inventories to Brand Model for non-tagged accessories
            $table->id();
            $table->foreignId('inventory_id')->nullable()
              ->constrained('inventories')
              ->cascadeOnDelete()->comment('Inventories where item_type_id is Desktop/CPU');
            $table->foreignId('brand_model_id')->nullable()
              ->constrained()
              ->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            // $table->unique(['desktop_inventory_id', 'brand_model_id']); // One of each type of accessory per desktop

            // This maps to my AssetAccessories.

            // id, desktop_inventory_id (FK to Inventories.id where item_type_id is 'Desktop/CPU'), brand_model_id (FK to Brand Model), notes (nullable).

            // Unique constraint: (desktop_inventory_id, brand_model_id).
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_accessories');
    }
};
