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
             $table->boolean('use_internet')->after('use_plotter')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_entries', function (Blueprint $table) {
             $table->dropColumn(['use_internet']);
        });
    }
};
