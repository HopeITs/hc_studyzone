<?php

namespace Database\Seeders;

use App\Models\Street;
use Illuminate\Database\Seeder;

class StreetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $streets = config('streets');

        foreach ($streets as $street) {
            Street::create([
                'name' => [
                    'en' => $street['name_en'],
                    'ar' => $street['name_ar']
                ],
                'city_id' => $street['city_id'],
            ]);
        }
    }
}
