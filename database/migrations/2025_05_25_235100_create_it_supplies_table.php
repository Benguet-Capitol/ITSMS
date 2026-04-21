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
        Schema::create('it_supplies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_model_id')
              ->constrained()
              ->cascadeOnDelete();
            $table->foreignId('measurement_unit_id')
              ->constrained()
              ->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->string('item_number')->nullable();
            $table->string('stock_number')->nullable();
            $table->string('ics_number')->comment('Inventory Custodian Slip Number')->nullable();
            $table->string('iar_number')->comment('Inspection and Acceptance Report Number')->nullable();
            $table->string('po_number')->comment('Purchase Order Number')->nullable();
            $table->integer('quantity')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('it_supplies');
    }
};
