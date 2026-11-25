<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('center_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('entry_type');
            $table->unsignedInteger('amount');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->timestamps();
            $table->unique(['center_id', 'entry_type', 'starts_on']);
        });

        Schema::table('student_entries', function (Blueprint $table): void {
            $table->unsignedInteger('daily_admission_fee')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('student_entries', function (Blueprint $table): void {
            $table->dropColumn('daily_admission_fee');
        });

        Schema::dropIfExists('admission_prices');
    }
};
