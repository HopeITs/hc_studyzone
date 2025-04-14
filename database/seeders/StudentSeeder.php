<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\StudentEducation;
use App\Models\StudentEntry;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 1; $i <= 50; $i++) {
            $student = Student::factory()->create();
            StudentEntry::factory(rand(1,50))->create(['student_id' => $student->id]);
            StudentEducation::factory(1)->create(['student_id' => $student->id]);
        }
    }
}
