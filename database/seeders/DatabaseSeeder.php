<?php

namespace Database\Seeders;

use App\Enums\Roles;
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

        User::factory()->create([
            'name' => 'Administrator',
            'email' => 'admin@sz.com',
            'role' => Roles::GeneralAdmin
        ]);

        User::factory()->create([
            'name' => 'Administrator',
            'email' => 'admin_city@sz.com',
            'city_id' => 2,
            'role' => Roles::CityAdmin
        ]);
        $this->call(CenterSeeder::class);
        $this->call(EducationSeeder::class);
        $this->call(StudentSeeder::class);
        $this->call(SocialLinksSeeder::class);
    }
}
