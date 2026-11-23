<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centers', function (Blueprint $table): void {
            $table->unsignedSmallInteger('seat_capacity')->nullable();
            $table->unsignedSmallInteger('micromotor_count')->nullable();
            $table->unsignedSmallInteger('drafting_table_count')->nullable();
            $table->unsignedSmallInteger('hama_count')->nullable();
            $table->boolean('internet_available')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('centers', function (Blueprint $table): void {
            $table->dropColumn([
                'seat_capacity',
                'micromotor_count',
                'drafting_table_count',
                'hama_count',
                'internet_available',
            ]);
        });
    }
};
