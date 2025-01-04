<?php

use App\Constants\TableConstants;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(TableConstants::CITIES, function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->timestamps();
        });


        Schema::create(TableConstants::CENTERS, function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(TableConstants::CITIES)->constrained();
            $table->json('name');
            $table->dateTime('start_date');
            $table->json('work_hours')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->json('address')->nullable();
            $table->timestamps();
        });

        Schema::table(TableConstants::USERS, function (Blueprint $table) {
            $table->foreignIdFor(TableConstants::CENTERS)->constrained();
            $table->boolean('is_active')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(TableConstants::CITIES);
        Schema::dropIfExists(TableConstants::CENTERS);
    }
};
