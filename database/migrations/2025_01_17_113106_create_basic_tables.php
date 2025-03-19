<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Cities Table
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->string('code')->unique();
            $table->timestamp('synced_at')->nullable()->default(null);
            $table->timestamps();
        });

        Schema::create('streets', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->foreignId('city_id')->constrained()->onDelete('cascade');
            $table->timestamp('synced_at')->nullable()->default(null);
            $table->timestamps();
        });

        // Centers Table
        Schema::create('centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained()->onDelete('cascade');
            $table->foreignId('street_id')->nullable()->constrained()->onDelete('cascade');
            $table->json('name');
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('synced_at')->nullable()->default(null);
            $table->timestamps();
            $table->index(['synced_at'],'idx_centers');
        });

        // Users Table Modifications
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('center_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('city_id')->nullable()->constrained()->onDelete('set null');
            $table->boolean('is_active')->default(true);
            $table->timestamp('synced_at')->nullable()->default(null);
            $table->index(['synced_at' , 'city_id' , 'is_active' , 'center_id'],'idx_users');
        });

        // Students Table
        Schema::create('students', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(Str::uuid());
            $table->foreignId('street_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('firstname');
            $table->string('lastname');
            $table->string('father_name');
            $table->string('national_number')->unique()->nullable();
            $table->string('phone')->unique()->nullable();
            $table->string('whatsapp')->unique()->nullable();
            $table->date('year_of_birth')->nullable();
            $table->string('email')->nullable()->unique();
            $table->tinyInteger('gender')->default(0);
            $table->tinyInteger('religion')->default(0);
            $table->string('address')->nullable();
            $table->string('notes')->nullable();
            $table->boolean('is_student')->default(true);
            $table->boolean('is_employed')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('synced_at')->nullable()->default(null);

            $table->timestamps();

            $table->index(['synced_at' , 'street_id' , 'is_active' , 'gender'],'idx_students');

        });

        Schema::create('educations', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->tinyInteger('degree')->default(0);
            $table->timestamp('synced_at')->nullable()->default(null);
            $table->timestamps();

            $table->index(['synced_at' , 'degree' ],'idx_educations');

        });

        Schema::create('student_educations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(Str::uuid());
            $table->foreignId('education_id')->constrained('educations')->onDelete('cascade');
            $table->foreignUuid('student_id')->constrained('students')->onDelete('cascade');
            $table->tinyInteger('level')->default(0);
            $table->tinyInteger('type')->default(0);
            $table->timestamp('synced_at')->nullable()->default(null);
            $table->timestamps();
            $table->index(['id', 'education_id', 'student_id' , 'level' , 'type' , 'synced_at'],
                'idx_student_edu');

        });


        // Student Entries Table
        Schema::create('student_entries', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(Str::uuid());
            $table->foreignUuid('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('center_id')->constrained('centers')->onDelete('cascade');
            $table->tinyInteger('entry_type')->default(1);
            $table->boolean('use_printer')->default(false);
            $table->boolean('use_drawer')->default(false);
            $table->boolean('use_mecrator')->default(false);
            $table->dateTime('entry_time')->default(now());
            $table->timestamp('synced_at')->nullable()->default(null);
            $table->timestamps();

            // Indexes for performance
            $table->index(['student_id', 'center_id', 'entry_time','synced_at'],'idx_student_entry');
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
        Schema::dropIfExists('users');
        Schema::dropIfExists('centers');
        Schema::dropIfExists('streets');
        Schema::dropIfExists('cities');

    }
};
