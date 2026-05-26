<?php

namespace Database\Seeders;

use App\Models\ServicePrice;
use Illuminate\Database\Seeder;

class ServicePriceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $prices = [
            ['service_id' => 1, 'center_id' => 6, 'price' => 1500],
            ['service_id' => 2, 'center_id' => 6, 'price' => 5000],
            ['service_id' => 48, 'center_id' => 6, 'price' => 200],
            ['service_id' => 49, 'center_id' => 6, 'price' => 400],
            ['service_id' => 33, 'center_id' => 6, 'price' => 300],
            ['service_id' => 32, 'center_id' => 6, 'price' => 500],
            ['service_id' => 34, 'center_id' => 6, 'price' => 7000],
            ['service_id' => 23, 'center_id' => 6, 'price' => 100],
            ['service_id' => 31, 'center_id' => 6, 'price' => 50000],
            ['service_id' => 1, 'center_id' => 3, 'price' => 1500],
            ['service_id' => 31, 'center_id' => 3, 'price' => 50000],
            ['service_id' => 23, 'center_id' => 3, 'price' => 100],
            ['service_id' => 48, 'center_id' => 3, 'price' => 200],
            ['service_id' => 49, 'center_id' => 3, 'price' => 400],
            ['service_id' => 29, 'center_id' => 15, 'price' => 5000],
            ['service_id' => 1, 'center_id' => 15, 'price' => 1500],
            ['service_id' => 2, 'center_id' => 15, 'price' => 5000],
            ['service_id' => 34, 'center_id' => 15, 'price' => 7000],
            ['service_id' => 30, 'center_id' => 15, 'price' => 50000],
            ['service_id' => 23, 'center_id' => 15, 'price' => 100],
            ['service_id' => 35, 'center_id' => 15, 'price' => 20000],
            ['service_id' => 36, 'center_id' => 15, 'price' => 10000],
            ['service_id' => 37, 'center_id' => 15, 'price' => 15000],
            ['service_id' => 38, 'center_id' => 15, 'price' => 7500],
            ['service_id' => 39, 'center_id' => 15, 'price' => 4000],
            ['service_id' => 40, 'center_id' => 15, 'price' => 25000],
            ['service_id' => 41, 'center_id' => 15, 'price' => 12500],
            ['service_id' => 42, 'center_id' => 15, 'price' => 60000],
            ['service_id' => 43, 'center_id' => 15, 'price' => 30000],
            ['service_id' => 44, 'center_id' => 15, 'price' => 30000],
            ['service_id' => 45, 'center_id' => 15, 'price' => 15000],
            ['service_id' => 46, 'center_id' => 15, 'price' => 50000],
            ['service_id' => 47, 'center_id' => 15, 'price' => 25000],
            ['service_id' => 33, 'center_id' => 15, 'price' => 300],
            ['service_id' => 32, 'center_id' => 15, 'price' => 500],
            ['service_id' => 48, 'center_id' => 15, 'price' => 200],
            ['service_id' => 4, 'center_id' => 15, 'price' => 400],
            ['service_id' => 1, 'center_id' => 17, 'price' => 1500],
            ['service_id' => 29, 'center_id' => 17, 'price' => 5000],
            ['service_id' => 23, 'center_id' => 17, 'price' => 200],
            ['service_id' => 31, 'center_id' => 17, 'price' => 30000], // تم تعديل 30,000 إلى 30000
            ['service_id' => 3, 'center_id' => 17, 'price' => 300],
            ['service_id' => 4, 'center_id' => 17, 'price' => 500],
            ['service_id' => 49, 'center_id' => 4, 'price' => 300],
            ['service_id' => 32, 'center_id' => 4, 'price' => 500],
            ['service_id' => 23, 'center_id' => 4, 'price' => 200],
            ['service_id' => 1, 'center_id' => 8, 'price' => 1500],
            ['service_id' => 49, 'center_id' => 8, 'price' => 300],
            ['service_id' => 32, 'center_id' => 8, 'price' => 500],
            ['service_id' => 9, 'center_id' => 8, 'price' => 1000],
            ['service_id' => 23, 'center_id' => 8, 'price' => 200],
            ['service_id' => 25, 'center_id' => 8, 'price' => 500],
            ['service_id' => 50, 'center_id' => 8, 'price' => 50000],
            ['service_id' => 49, 'center_id' => 12, 'price' => 300],
            ['service_id' => 32, 'center_id' => 12, 'price' => 500],
            ['service_id' => 9, 'center_id' => 12, 'price' => 1000],
            ['service_id' => 23, 'center_id' => 12, 'price' => 200],
            ['service_id' => 25, 'center_id' => 12, 'price' => 500],
            ['service_id' => 50, 'center_id' => 12, 'price' => 50000],
            ['service_id' => 3, 'center_id' => 14, 'price' => 300],
            ['service_id' => 4, 'center_id' => 14, 'price' => 400],
            ['service_id' => 5, 'center_id' => 14, 'price' => 500],
            ['service_id' => 6, 'center_id' => 14, 'price' => 600],
            ['service_id' => 7, 'center_id' => 14, 'price' => 2000],
            ['service_id' => 8, 'center_id' => 14, 'price' => 3000],
            ['service_id' => 9, 'center_id' => 14, 'price' => 600],
            ['service_id' => 10, 'center_id' => 14, 'price' => 800],
            ['service_id' => 11, 'center_id' => 14, 'price' => 1000],
            ['service_id' => 12, 'center_id' => 14, 'price' => 1200],
            ['service_id' => 13, 'center_id' => 14, 'price' => 150],
            ['service_id' => 14, 'center_id' => 14, 'price' => 200],
            ['service_id' => 15, 'center_id' => 14, 'price' => 250],
            ['service_id' => 16, 'center_id' => 14, 'price' => 300],
            ['service_id' => 17, 'center_id' => 14, 'price' => 12000],
            ['service_id' => 18, 'center_id' => 14, 'price' => 15000],
            ['service_id' => 19, 'center_id' => 14, 'price' => 18000],
            ['service_id' => 20, 'center_id' => 14, 'price' => 20000],
            ['service_id' => 21, 'center_id' => 14, 'price' => 15000],
            ['service_id' => 22, 'center_id' => 14, 'price' => 20000],
            ['service_id' => 23, 'center_id' => 14, 'price' => 200],
            ['service_id' => 24, 'center_id' => 14, 'price' => 1500],
            ['service_id' => 25, 'center_id' => 14, 'price' => 1000],
            ['service_id' => 26, 'center_id' => 14, 'price' => 2000],
            ['service_id' => 27, 'center_id' => 14, 'price' => 100],
            ['service_id' => 28, 'center_id' => 14, 'price' => 10000],
            ['service_id' => 50, 'center_id' => 14, 'price' => 50000],
            ['service_id' => 29, 'center_id' => 14, 'price' => 5000],
            ['service_id' => 1, 'center_id' => 14, 'price' => 1500],
            ['service_id' => 2, 'center_id' => 14, 'price' => 5000],
            ['service_id' => 1, 'center_id' => 19, 'price' => 1500],
            ['service_id' => 2, 'center_id' => 19, 'price' => 5000],
            ['service_id' => 49, 'center_id' => 19, 'price' => 500],
            ['service_id' => 32, 'center_id' => 19, 'price' => 1000],
            ['service_id' => 23, 'center_id' => 19, 'price' => 200],
            ['service_id' => 50, 'center_id' => 19, 'price' => 60000],
            ['service_id' => 10, 'center_id' => 19, 'price' => 2000],
        ];

        // المعامل الأول: البيانات
        // المعامل الثاني: الأعمدة الفريدة التي يتحدد بناءً عليها إذا كان السجل موجوداً مسبقاً
        // المعامل الثالث: الأعمدة التي سيتم تحديثها إذا كان السجل موجوداً (في هذه الحالة السعر)
        ServicePrice::upsert($prices, ['service_id', 'center_id'], ['price']);
    }
}
