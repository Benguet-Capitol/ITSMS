<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who is working on a request -- the request-module counterpart of
 * ticket_personnel, shared by every request type through a morph
 * (docs/adr/0002-request-workflow.md). assigned_by_profile_id is null when
 * the person accepted the request themselves, and otherwise records the
 * admin who assigned them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_personnel', function (Blueprint $table) {
            $table->id();
            $table->morphs('requestable');
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by_profile_id')->nullable()->constrained('profiles')->nullOnDelete();
            $table->timestamps();

            $table->unique(['requestable_type', 'requestable_id', 'profile_id'], 'request_personnel_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_personnel');
    }
};
