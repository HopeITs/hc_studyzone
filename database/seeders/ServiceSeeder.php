<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            ['id' => 1, 'name' => 'مرسم', 'category_id' => 2, 'reference_column_name' => 'use_drawer'],
            ['id' => 2, 'name' => 'ميكراتور', 'category_id' => 2, 'reference_column_name' => 'use_mecrator'],
            ['id' => 3, 'name' => 'طباعة وجه ( اسود - ملون ) A4', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 4, 'name' => 'طباعة وجهين ( اسود - ملون ) A4', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 5, 'name' => 'طباعة وجه ( صور ) A4', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 6, 'name' => 'طباعة وجهين ( صور ) A4', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 7, 'name' => 'طباعة كرتون ( كتابة ) A4', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 8, 'name' => 'طباعة ( صور ) A4', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 9, 'name' => 'طباعة وجه ( اسود - ملون ) A3', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 10, 'name' => 'طباعة وجهين ( اسود - ملون ) A3', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 11, 'name' => 'طباعة وجه ( صور ) A3', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 12, 'name' => 'طباعة وجهين ( صور ) A3', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 13, 'name' => 'طباعة وجه ( اسود - ملون ) A5', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 14, 'name' => 'طباعة وجهين ( اسود - ملون ) A5', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 15, 'name' => 'طباعة وجه ( صور ) A5', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 16, 'name' => 'طباعة وجهين ( صور ) A5', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 17, 'name' => 'طباعة كرتون ( كتابة ) A1', 'category_id' => 3, 'reference_column_name' => 'use_plotter'],
            ['id' => 18, 'name' => 'طباعة كرتون ( صور ) A1', 'category_id' => 3, 'reference_column_name' => 'use_plotter'],
            ['id' => 19, 'name' => 'طباعة كرتون ( كتابة ) A0', 'category_id' => 3, 'reference_column_name' => 'use_plotter'],
            ['id' => 20, 'name' => 'طباعة كرتون ( صور ) A0', 'category_id' => 3, 'reference_column_name' => 'use_plotter'],
            ['id' => 21, 'name' => 'رول متر ( كتابة )', 'category_id' => 3, 'reference_column_name' => 'use_plotter'],
            ['id' => 22, 'name' => 'رول متر ( صور )', 'category_id' => 3, 'reference_column_name' => 'use_plotter'],
            ['id' => 23, 'name' => 'ورق ابيض A4', 'category_id' => 3, 'reference_column_name' => 'stationary'],
            ['id' => 24, 'name' => 'كرتون A4', 'category_id' => 3, 'reference_column_name' => 'stationary'],
            ['id' => 25, 'name' => 'ورق A3', 'category_id' => 3, 'reference_column_name' => 'stationary'],
            ['id' => 26, 'name' => 'كرتون A3', 'category_id' => 3, 'reference_column_name' => 'stationary'],
            ['id' => 27, 'name' => 'ورق ابيض A5', 'category_id' => 3, 'reference_column_name' => 'stationary'],
            ['id' => 28, 'name' => 'كرتون كونسون A0', 'category_id' => 3, 'reference_column_name' => 'stationary'],
            ['id' => 29, 'name' => 'انترنت', 'category_id' => 1, 'reference_column_name' => 'use_internet'],
            ['id' => 30, 'name' => 'حجز قاعة سايلنت - يوم', 'category_id' => 1, 'reference_column_name' => 'reservation'],
            ['id' => 31, 'name' => 'حجز قاعة سايلنت - ساعة', 'category_id' => 2, 'reference_column_name' => 'reservation'],
            ['id' => 32, 'name' => 'طباعة ورق ملون A4 وجهين', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 33, 'name' => 'طباعة ورق ملون A4 وجه', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 34, 'name' => 'راصور دفتر', 'category_id' => 3, 'reference_column_name' => 'stationary'],
            ['id' => 35, 'name' => 'طباعة طبق كانسون', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 36, 'name' => 'طباعة نصف كانسون', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 37, 'name' => 'طباعة طبق ورق', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 38, 'name' => 'طباعة نصف طبق ورق', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 39, 'name' => 'طباعة ربع طبق ورق', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 40, 'name' => 'طباعة طبق ورق ملون', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 41, 'name' => 'طباعة نصف طبق ورق ملون', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 42, 'name' => 'طباعة طبق غلوسي', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 43, 'name' => 'طباعة نصف طبق غلوسي', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 44, 'name' => 'طباعة طبق كانسون ملون', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 45, 'name' => 'طباعة نصف طبق كانسون ملون', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 46, 'name' => 'طباعة طبق كوتد', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 47, 'name' => 'طباعة نصف طبق كوتد', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 48, 'name' => 'طباعة وجه اسود وابيض A4', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 49, 'name' => 'طباعة وجهين ابيض اسود A4', 'category_id' => 3, 'reference_column_name' => 'use_printer'],
            ['id' => 50, 'name' => 'حجز قاعة Quiet - ساعة', 'category_id' => 2, 'reference_column_name' => 'reservation'],
        ];
        foreach ($services as $service) {
            Service::create($service);
        }
    }
}
