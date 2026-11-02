<?php

namespace App\Exports;

use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class InventoryItemsExport implements FromCollection, WithHeadings
{
    public function __construct(protected Builder $query)
    {
    }

    public function collection(): Collection
    {
        return $this->query
            ->withSum('movements as stock_sum', 'quantity')
            ->with('center')
            ->get()
            ->map(fn (InventoryItem $item) => [
                $item->name,
                $item->category,
                __('inventory.units.'.$item->unit->value),
                (int) ($item->stock_sum ?? 0),
                $item->min_stock_threshold,
                $item->purchase_cost,
                $item->sale_price,
                $item->supplier,
                $item->last_purchase_date?->format('Y-m-d'),
                $item->center?->name,
                $item->is_active
                    ? __('inventory.filters.active')
                    : __('inventory.filters.inactive'),
            ]);
    }

    public function headings(): array
    {
        return [
            __('inventory.fields.name'),
            __('inventory.fields.category'),
            __('inventory.fields.unit'),
            __('inventory.fields.current_stock'),
            __('inventory.fields.min_stock_threshold'),
            __('inventory.fields.purchase_cost'),
            __('inventory.fields.sale_price'),
            __('inventory.fields.supplier'),
            __('inventory.fields.last_purchase_date'),
            __('inventory.fields.center'),
            __('inventory.fields.is_active'),
        ];
    }
}
