<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cities = [
            [
                'name' => ['en' => 'Damascus', 'ar' => 'دمشق'],
                'code' => 'damascus',
            ],
            [
                'name' => ['en' => 'Aleppo', 'ar' => 'حلب'],
                'code' => 'aleppo',
            ],
            [
                'name' => ['en' => 'Homs', 'ar' => 'حمص'],
                'code' => 'homs'
            ],
            [
                'name' => ['en' => 'Latakia', 'ar' => 'اللاذقية'],
                'code' => 'latakia'
            ],
            [
                'name' => ['en' => 'Tartous', 'ar' => 'طرطوس'],
                'code' => 'tartous'
            ],
        ];


        foreach ($cities as $city) {
            City::create($city);
        }
    }
}
