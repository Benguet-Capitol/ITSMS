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
        Schema::create('item_types', function (Blueprint $table) {
            $table->id();
            $table->string('classification')->nullable();
            $table->string('purpose')->nullable();
            $table->string('type');
            $table->boolean('is_main_inventory')->default(false)->comment('If this item type can be a standalone, tagged inventory with its own property number (e.g., Desktop/CPU, Monitor, Printer, UPS)');
            $table->boolean('is_component')->default(false)->comment('If this item type is typically an internal component of a main inventory or a simple accessory (e.g., Processor, Motherboard, Memory Module, Storage, Video Card, Mouse, Keyboard, Webcam, Speaker)');
            // Some items like 'Monitor', 'Printer', 'UPS' are both is_main_inventory AND is_component because they can exist as a main inventory themselves, but also have a ComponentModel entry and can be associated as a "component/peripheral" to a Desktop.
            $table->string('part_number')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_types');
    }
};
