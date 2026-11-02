<?php

namespace Database\Seeders;

use App\Models\Center;
use App\Models\InventoryItem;
use Illuminate\Database\Seeder;

/**
 * Starter buffet catalog, copied into every active center so each
 * center prices and stocks its own copy independently.
 *
 * Run on demand only: php artisan db:seed --class=InventoryItemSeeder
 */
class InventoryItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'مياه معدنية صغيرة', 'category' => 'مشروبات', 'unit' => 'piece', 'purchase_cost' => 0, 'sale_price' => 0, 'min_stock_threshold' => 24],
            ['name' => 'عصير علبة', 'category' => 'مشروبات', 'unit' => 'piece', 'purchase_cost' => 0, 'sale_price' => 0, 'min_stock_threshold' => 12],
            ['name' => 'قهوة سادة (كوب)', 'category' => 'مشروبات ساخنة', 'unit' => 'piece', 'purchase_cost' => 0, 'sale_price' => 0, 'min_stock_threshold' => 0],
            ['name' => 'شاي (كوب)', 'category' => 'مشروبات ساخنة', 'unit' => 'piece', 'purchase_cost' => 0, 'sale_price' => 0, 'min_stock_threshold' => 0],
            ['name' => 'نسكافيه (كوب)', 'category' => 'مشروبات ساخنة', 'unit' => 'piece', 'purchase_cost' => 0, 'sale_price' => 0, 'min_stock_threshold' => 0],
            ['name' => 'بسكويت', 'category' => 'سناكس', 'unit' => 'piece', 'purchase_cost' => 0, 'sale_price' => 0, 'min_stock_threshold' => 12],
            ['name' => 'شيبس', 'category' => 'سناكس', 'unit' => 'piece', 'purchase_cost' => 0, 'sale_price' => 0, 'min_stock_threshold' => 12],
            ['name' => 'شوكولا', 'category' => 'سناكس', 'unit' => 'piece', 'purchase_cost' => 0, 'sale_price' => 0, 'min_stock_threshold' => 12],
        ];

        Center::query()->where('is_active', true)->get()->each(function (Center $center) use ($items) {
            foreach ($items as $item) {
                InventoryItem::query()->firstOrCreate(
                    ['center_id' => $center->id, 'name' => $item['name']],
                    $item + ['is_active' => true],
                );
            }
        });
    }
}
