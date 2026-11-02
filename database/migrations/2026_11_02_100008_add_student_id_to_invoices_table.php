<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Optional link to a registered student. beneficiary_name stays a
            // snapshot so walk-in customers (non-students) keep working and
            // old invoices are untouched.
            $table->foreignUuid('student_id')
                ->nullable()
                ->after('student_number')
                ->constrained('students')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('student_id');
        });
    }
};
