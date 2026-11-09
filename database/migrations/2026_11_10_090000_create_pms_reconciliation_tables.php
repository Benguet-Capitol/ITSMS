<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Counter-checking inventory against PGSO's property management system
 * (docs/inventory-reconciliation.md). PMS is only ever read: its list is
 * copied into pms_properties by `php artisan pms:sync`, and the other
 * tables hold what ITSMS staff decided about the differences.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A copy of the PMS feed; the id is PMS's own property_id.
        Schema::create('pms_properties', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('main_id')->nullable()->index();
            $table->string('property_name')->nullable();
            $table->string('property_no');
            // Upper case, no whitespace: what matching compares.
            $table->string('property_no_key')->index();
            // The key without prefixes like "ICS " or "TF": likely matches only.
            $table->string('property_no_core')->index();
            $table->string('property_unit')->nullable();
            $table->decimal('property_price', 15, 2)->nullable();
            $table->text('property_details')->nullable();
            // Read out of property_details, which is free text.
            $table->string('serial_number')->nullable();
            $table->string('full_name')->nullable();
            $table->string('office_code')->nullable()->index();
            $table->timestamp('synced_at');
        });

        Schema::create('pms_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status'); // running, succeeded, failed
            $table->foreignId('triggered_by_profile_id')->nullable()->constrained('profiles')->nullOnDelete();
            $table->unsignedInteger('row_count')->nullable();
            $table->unsignedInteger('skipped_count')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
        });

        // Which HRIS office a PMS office name stands for.
        Schema::create('pms_office_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('pms_office_code')->unique();
            $table->unsignedBigInteger('office_id');
            $table->string('office_code')->nullable();
            $table->string('office_name')->nullable();
            $table->foreignId('updated_by_profile_id')->nullable()->constrained('profiles')->nullOnDelete();
            $table->timestamps();
        });

        // An item matched to a PMS property by hand, when the numbers differ.
        Schema::create('pms_inventory_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->unique()->constrained('inventories')->cascadeOnDelete();
            $table->unsignedBigInteger('pms_property_id')->unique();
            $table->foreignId('linked_by_profile_id')->nullable()->constrained('profiles')->nullOnDelete();
            $table->timestamps();
        });

        // A difference someone checked and decided to keep. The fingerprint
        // is the values compared at the time: if either side changes, the
        // difference comes back.
        Schema::create('pms_reconciliation_dismissals', function (Blueprint $table) {
            $table->id();
            $table->string('issue');
            $table->foreignId('inventory_id')->nullable()->constrained('inventories')->cascadeOnDelete();
            $table->unsignedBigInteger('pms_property_id')->nullable();
            $table->string('fingerprint', 32);
            $table->string('note', 500)->nullable();
            $table->foreignId('dismissed_by_profile_id')->nullable()->constrained('profiles')->nullOnDelete();
            $table->timestamps();

            $table->unique(['issue', 'inventory_id', 'pms_property_id'], 'pms_dismissals_subject_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pms_reconciliation_dismissals');
        Schema::dropIfExists('pms_inventory_links');
        Schema::dropIfExists('pms_office_mappings');
        Schema::dropIfExists('pms_sync_runs');
        Schema::dropIfExists('pms_properties');
    }
};
