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
                'name' => 'Damascus',
                'code' => 'damascus',
            ],
            [
                'name' => 'Aleppo',
                'code' => 'aleppo',
            ],
            [
                'name' => 'Homs',
                'code' => 'homs'
            ],
            [
                'name' => 'Latakia',
                'code' => 'latakia'
            ],
            [
                'name' => 'Tartous',
                'code' => 'tartous'
            ],
        ];

        foreach ($cities as $city) {
            City::create($city);
        }
    }
}
