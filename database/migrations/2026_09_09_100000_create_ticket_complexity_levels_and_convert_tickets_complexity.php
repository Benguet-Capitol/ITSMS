<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The 3 levels this app ships with today, carried over verbatim from
     * the hardcoded enum/bracket map they replace. Seeded here (not via a
     * separate Seeder) so this migration is the single, self-contained
     * place that both creates the table and backfills existing tickets --
     * a standalone Seeder run via `migrate:fresh --seed` would otherwise
     * risk a duplicate insert against the unique `label` column.
     */
    private const DEFAULT_LEVELS = [
        [
            'label' => 'Simple',
            'description' => 'Routine assistance that can be resolved quickly with standard procedures.',
            'examples' => 'Password reset, printer connection, basic software setup, cable reconnection, basic troubleshooting, cleaning, replacement of minor parts, and routine maintenance.',
            'min_minutes' => 0,
            'max_minutes' => 30,
            'color' => 'info',
            'sort_order' => 1,
            'legacy_key' => 'simple',
        ],
        [
            'label' => 'Moderate',
            'description' => 'Requires technical troubleshooting, configuration, or some hardware/software intervention.',
            'examples' => 'Network troubleshooting, OS issues, software installation, hardware replacement, printer malfunction, requires disassembly or replacement of components such as RAM, SSD/HDD, fan, battery, or power supply.',
            'min_minutes' => 31,
            'max_minutes' => 120,
            'color' => 'warning',
            'sort_order' => 2,
            'legacy_key' => 'moderate',
        ],
        [
            'label' => 'Complex',
            'description' => 'Requires advanced technical knowledge, extensive diagnosis, system-level intervention, or coordination with other technical personnel/vendors.',
            'examples' => 'Server issues, network infrastructure problems, database/system issues, motherboard repair, major system errors, requires extensive diagnosis, specialized tools, motherboard repair/replacement, board-level work, or advanced troubleshooting.',
            'min_minutes' => 121,
            'max_minutes' => null,
            'color' => 'error',
            'sort_order' => 3,
            'legacy_key' => 'complex',
        ],
    ];

    public function up(): void
    {
        Schema::create('ticket_complexity_levels', function (Blueprint $table) {
            $table->id();
            $table->string('label')->unique();
            $table->text('description')->nullable();
            $table->text('examples')->nullable();
            $table->unsignedInteger('min_minutes')->default(0);
            $table->unsignedInteger('max_minutes')->nullable();
            $table->string('color')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        $levelIdsByLegacyKey = [];

        foreach (self::DEFAULT_LEVELS as $level) {
            $legacyKey = $level['legacy_key'];
            unset($level['legacy_key']);

            $levelIdsByLegacyKey[$legacyKey] = DB::table('ticket_complexity_levels')->insertGetId([
                ...$level,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('complexity_level_id')
                ->nullable()
                ->after('complexity')
                ->constrained('ticket_complexity_levels')
                ->restrictOnDelete();
        });

        // No-op when `tickets` is already empty (e.g. a fresh deploy that
        // truncates before this runs) -- otherwise maps every existing
        // row's free-text value onto the newly seeded levels above.
        foreach ($levelIdsByLegacyKey as $legacyKey => $levelId) {
            DB::table('tickets')->where('complexity', $legacyKey)->update([
                'complexity_level_id' => $levelId,
            ]);
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('complexity');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('complexity')->nullable()->after('service_method');
        });

        DB::table('tickets')
            ->join('ticket_complexity_levels', 'tickets.complexity_level_id', '=', 'ticket_complexity_levels.id')
            ->update([
                'tickets.complexity' => DB::raw('LOWER(ticket_complexity_levels.label)'),
            ]);

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('complexity_level_id');
        });

        Schema::dropIfExists('ticket_complexity_levels');
    }
};
