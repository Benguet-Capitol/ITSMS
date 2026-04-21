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
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            // Basic Information
            // $table->foreignId('employee_id')->comment('Issued To')->nullable()
            //   ->constrained()
            //   ->nullOnDelete();

            $table->unsignedBigInteger('employee_id')->nullable()->comment('Issued To (HRIS employee PK id)');
            $table->index('employee_id');

            $table->foreignId('item_type_id')->nullable() // The general classification of the physical asset.
              ->constrained()
              ->nullOnDelete();
            $table->foreignId('brand_model_id')->nullable() // The specific product model for this type of physical asset, if applicable.
              ->constrained()
              ->nullOnDelete()->comment('This links a primary asset (like an Acer SA272Q Monitor, an Epson L360 Printer, or an APC BX625CI-MS UPS) to its specific Brand Model entry. For a generic assembled Desktop/CPU, this might remain NULL');
            $table->foreignId('parent_component_id')->nullable() // The specific product model for this type of physical asset, if applicable.
              ->constrained('inventories')
              ->nullOnDelete()->comment('For some item_type like Monitor, Printer, UPS, etc. this links to the parent component. For example, a Monitor might be linked to a Desktop/CPU.');
            $table->string('ip_address')->nullable();
            $table->string('mac_address')->nullable();
            $table->text('remarks')->nullable();
            // Desktop/CPU specific fields
            $table->string('operating_system_name')->nullable();
            $table->string('os_license_number')->nullable();
            $table->string('anti_virus_name')->nullable();
            $table->string('anti_virus_license_number')->nullable();
            $table->string('microsoft_office_name')->nullable();
            $table->string('ms_office_license_number')->nullable();
            $table->text('other_installed_applications')->nullable();

            $table->string('property_number');
            $table->timestamp('date_acquired')->nullable();
            $table->timestamp('warranty_expiration_date')->nullable();
            $table->string('serial_number')->nullable()->comment('For peripherals like Monitors, Printers, UPS.');
            $table->string('status')->nullable()->comment('Deployed, In Storage, Repair, Disposed');

            // $table->string('parent_component')->nullable();
            // $table->integer('code')->nullable();
            // $table->string('barcode')->nullable();
            // $table->text('description')->nullable();
            // $table->string('control_number')->comment('Pre and Post - Repair Inspection Report Number / Control Number')->nullable();
            // $table->timestamp('date_issued')->nullable();
            
            // $table->timestamp('date_accepted')->nullable();
            // $table->timestamp('date_installed')->nullable();
            
            // $table->string('inventory_type')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
