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
        Schema::create('inventory_associations', function (Blueprint $table) {
            // Other Primary Assets (like a Monitor or Printer) that are connected to a main Primary Asset (like a Desktop)
            // Linking Inventories records to other Inventories records
            $table->id();
            
            $table->foreignId('source_asset_id')->nullable()
              ->constrained('inventories')
              ->cascadeOnDelete()->comment('Inventories typically the Desktop/CPU'); // (FK to Inventories.id, typically the Desktop)
            $table->foreignId('target_asset_id')->nullable()
              ->constrained('inventories')
              ->cascadeOnDelete()->comment('Inventories typically the Monitor, Printer, UPS, etc.'); // (FK to Inventories.id, typically the Monitor, Printer, UPS)
            $table->string('association_type')->nullable()->comment('connected_to, uses');
            $table->string('connection_details')->nullable();
            $table->timestamps();
            $table->unique(['source_asset_id', 'target_asset_id']); // Prevent duplicate associations
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_associations');
    }
};
