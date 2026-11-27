<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monthly entries recorded from now on: the first visit of a student in a
 * center in a month pays the subscription ("charged"), the later visits of
 * that month are covered by it ("covered"). Entries recorded before keep
 * NULL, since the date their subscription was paid is unknown.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_entries', function (Blueprint $table): void {
            $table->string('monthly_status', 16)->nullable();
            $table->unsignedInteger('monthly_admission_fee')->nullable();
            $table->index(['student_id', 'center_id', 'entry_type', 'entry_time'], 'idx_student_entries_subscription');
        });
    }

    public function down(): void
    {
        Schema::table('student_entries', function (Blueprint $table): void {
            $table->dropIndex('idx_student_entries_subscription');
            $table->dropColumn(['monthly_status', 'monthly_admission_fee']);
        });
    }
};
