<?php

namespace Database\Seeders;

use App\Models\Center;
use App\Models\City;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CenterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
       $cities = City::where('code' , '!=' , 'na')->get();
       foreach ($cities as $city) {
           for ($i = 1; $i <= 2; $i++) {
               $center =  Center::factory()->create([
                   'city_id' => $city->id,
               ]);
               User::factory()->create([
                   'name' => 'Center Admin '.$center->name,
                   'city_id' => $city->id,
                   'center_id' => $center->id,
               ])->assignRole('center_admin');
           }


       }
    }
}
