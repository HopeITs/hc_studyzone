<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Cities Table
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->unique();
            $table->timestamps();
        });

        // Centers Table
        Schema::create('centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('address')->nullable();
            $table->timestamps();
        });

        // Users Table Modifications
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('center_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('city_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('role', ['general_admin', 'city_admin', 'center_admin'])->default('center_admin');
            $table->boolean('is_active')->default(true);
        });

        // Students Table
        Schema::create('students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('city_id')->constrained()->onDelete('cascade');
            $table->string('firstname');
            $table->string('lastname');
            $table->string('father_name');
            $table->string('national_number')->unique();
            $table->string('phone')->unique();
            $table->string('email')->nullable()->unique();
            $table->enum('gender', ['male', 'female'])->default('male');
            $table->enum('religion', ['christian', 'muslim', 'unknown'])->default('unknown');
            $table->string('address')->nullable();
            $table->boolean('is_student')->default(true);
            $table->boolean('is_employed')->default(false);

            $table->timestamps();

            // Indexes for performance
            $table->index(['city_id', 'national_number']);
        });

        Schema::create('educations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->tinyInteger('degree')->default(0);
            $table->timestamps();

        });

        Schema::create('student_educations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('education_id')->constrained('educations')->onDelete('cascade');
            $table->foreignUuid('student_id')->constrained('students')->onDelete('cascade');
            $table->tinyInteger('level')->default(0);
            $table->tinyInteger('type')->default(0);
            $table->timestamps();
            $table->index(['id', 'education_id', 'student_id']);
        });


        // Student Entries Table
        Schema::create('student_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('center_id')->constrained('centers')->onDelete('cascade');
            $table->tinyInteger('entry_type')->default(0);
            $table->dateTime('entry_time')->default(now());
            $table->softDeletes();
            $table->timestamps();

            // Indexes for performance
            $table->index(['student_id', 'center_id', 'entry_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_entries');
        Schema::dropIfExists('student_educations');
        Schema::dropIfExists('educations');
        Schema::dropIfExists('students');
        Schema::dropIfExists('centers');
        Schema::dropIfExists('cities');

    }
};
