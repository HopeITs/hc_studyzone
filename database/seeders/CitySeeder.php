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
                'name' => ['en' => 'N/A', 'ar' => 'لا شيء'],
                'code' => 'na',
            ],
            [
                'name' => ['en' => 'Homs', 'ar' => 'حمص'],
                'code' => 'homs'
            ],
            [
                'name' => ['en' => 'Damascus', 'ar' => 'دمشق'],
                'code' => 'damascus',
            ],
            [
                'name' => ['en' => 'Aleppo', 'ar' => 'حلب'],
                'code' => 'aleppo',
            ],
            [
                'name' => ['en' => 'Latakia', 'ar' => 'اللاذقية'],
                'code' => 'latakia'
            ],
            [
                'name' => ['en' => 'Homs Countryside', 'ar' => 'ريف حمص'],
                'code' => 'homs-countryside'
            ],
            [
                'name' => ['en' => 'Tartous', 'ar' => 'طرطوس'],
                'code' => 'tartous'
            ],
            [
                'name' => ['en' => 'Al Wadi', 'ar' => 'الوادي'],
                'code' => 'al-wadi'
            ],
        ];


        foreach ($cities as $city) {
            City::create($city);
        }
    }
}
