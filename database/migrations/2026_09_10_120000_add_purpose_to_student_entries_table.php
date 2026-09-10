<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_entries', function (Blueprint $table) {
            $table->tinyInteger('purpose')->default(0)->after('entry_type');
        });
    }

    public function down(): void
    {
        Schema::table('student_entries', function (Blueprint $table) {
            $table->dropColumn('purpose');
        });
    }
};
