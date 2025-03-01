<?php

namespace Database\Seeders;

use App\Models\Education;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(CitySeeder::class);
        $this->call(StreetSeeder::class);
        $this->call(RoleSeeder::class);
        User::factory()->create([
            'name' => 'Administrator',
            'email' => 'admin@sz.com',
        ])->assignRole('general_admin');

        User::factory()->create([
            'name' => 'Administrator',
            'email' => 'admin_city@sz.com',
            'city_id' => 2,
        ])->assignRole('city_admin');
        $this->call(CenterSeeder::class);
        $this->call(EducationSeeder::class);
        $this->call(StudentSeeder::class);




    }
}
