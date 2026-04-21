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
        Schema::create('inventory_internal_components', function (Blueprint $table) {
            // Parts that belong inside a Primary Asset (like a Desktop) and don't have their own tag
            // Mapping your existing Inventories to Brand Model for internal parts
            // item_type_id is 'Desktop/CPU'
            $table->id();
            $table->foreignId('inventory_id')->nullable()
              ->constrained('inventories')
              ->cascadeOnDelete()->comment('Inventories where item_type_id is Desktop/CPU');
            $table->foreignId('brand_model_id')->nullable()
              ->constrained()
              ->nullOnDelete();
            $table->string('specific_serial_number')->nullable();
            $table->string('slot')->nullable();
            $table->integer('quantity')->default(1);
            $table->text('notes')->nullable();
            // $table->unique(['desktop_asset_id', 'component_model_id', 'slot']); // Prevent duplicate component in same slot

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_internal_components');
    }
};
