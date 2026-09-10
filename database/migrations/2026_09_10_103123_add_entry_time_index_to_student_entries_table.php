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
        Schema::table('student_entries', function (Blueprint $table) {
            // Filters/widgets mostly query by date range, optionally narrowed by
            // center and entry_type; entry_time wasn't a leading column in any
            // existing index, so those queries fell back to full table scans.
            $table->index(['entry_time', 'center_id', 'entry_type'], 'idx_student_entries_time_center_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_entries', function (Blueprint $table) {
            $table->dropIndex('idx_student_entries_time_center_type');
        });
    }
};
