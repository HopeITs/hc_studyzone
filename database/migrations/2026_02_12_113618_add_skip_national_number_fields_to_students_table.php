<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->boolean('skip_national_number')->default(false)->after('national_number');
            $table->text('skip_national_number_reason')->nullable()->after('skip_national_number');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['skip_national_number', 'skip_national_number_reason']);
        });
    }
};
