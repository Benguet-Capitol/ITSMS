<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sprint 4 exit-gate item. Verified before dropping: `ticket_assignments`
     * holds 0 rows in the dev DB, `App\Models\TicketAssignment` had no
     * relation from `Ticket` and no controller/route referencing it
     * anywhere, and its seeder (`TicketAssignmentSeeder`) was an empty stub
     * never registered in `DatabaseSeeder`. Personnel-based acceptance
     * (`TicketPersonnel`, the `personnel()` relation) is the actual
     * mechanism in use -- this table was already fully superseded.
     */
    public function up(): void
    {
        Schema::dropIfExists('ticket_assignments');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('ticket_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('status');
            $table->timestamp('accepted_at');
            $table->timestamps();
        });
    }
};
