<?php

namespace Database\Seeders;

use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'خدمة دخولية', 'unit' => 'entry'],
            ['name' => 'خدمة ساعية', 'unit' => 'hour'],
            ['name' => 'خدمة ورقية', 'unit' => 'paper'],
        ];

        foreach ($categories as $category) {
            ServiceCategory::updateOrCreate(['unit' => $category['unit']], $category);
        }
    }
}