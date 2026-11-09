<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who filed each request (the staff member who took it from the client),
 * like tickets.profile_id. They're notified when the request is completed,
 * so they can tell the client -- clients come from HRIS and have no
 * account or email here. Null for requests filed before this column.
 */
return new class extends Migration
{
    private const TABLES = [
        'design_requests',
        'documentation_requests',
        'network_assistance_requests',
        'dts_user_requests',
        'other_it_service_requests',
        'cctv_footage_requests',
        'is_change_requests',
        'inspection_requests',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            if (! Schema::hasColumn($name, 'filed_by_profile_id')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->foreignId('filed_by_profile_id')
                        ->nullable()
                        ->constrained('profiles')
                        ->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            if (Schema::hasColumn($name, 'filed_by_profile_id')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('filed_by_profile_id');
                });
            }
        }
    }
};
