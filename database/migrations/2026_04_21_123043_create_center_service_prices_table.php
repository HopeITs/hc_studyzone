<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('center_service_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->integer('price')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['center_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('center_service_prices');
    }
};
