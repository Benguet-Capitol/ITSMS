<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The people who inspect deliveries (docs/requests-inspection.md). They're
 * not only IT staff -- the committee includes other offices -- so they're
 * their own list rather than user profiles, and an inspection request
 * picks one or more of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspectors', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('position')->nullable();
            // HRIS office, as on the request forms (HasRequesterOffice):
            // the description, plus its HRIS id and code.
            $table->string('office');
            $table->string('office_id')->nullable();
            $table->string('office_code')->nullable();
            // Inactive inspectors stay on past requests but aren't offered
            // for new ones.
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('inspection_request_inspector', function (Blueprint $table) {
            $table->foreignId('inspection_request_id')->constrained()->cascadeOnDelete();
            // An inspector on a request can't be deleted, only deactivated.
            $table->foreignId('inspector_id')->constrained()->restrictOnDelete();
            $table->primary(['inspection_request_id', 'inspector_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_request_inspector');
        Schema::dropIfExists('inspectors');
    }
};
