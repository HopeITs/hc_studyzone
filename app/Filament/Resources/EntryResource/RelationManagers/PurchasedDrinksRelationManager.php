<?php

declare(strict_types=1);

namespace App\Filament\Resources\EntryResource\RelationManagers;

use App\Enums\InvoiceStatus;
use App\Services\EntryInvoiceService;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PurchasedDrinksRelationManager extends RelationManager
{
    protected static string $relationship = 'purchasedLines';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('pos.entry.drinks');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->whereNotNull('inventory_item_id')
                ->whereHas('inventoryItem', fn (Builder $item): Builder => $item->where('category', EntryInvoiceService::DRINK_CATEGORY))
                ->whereHas('invoice', fn (Builder $invoice): Builder => $invoice->where('status', InvoiceStatus::Paid->value)))
            ->columns([
                TextColumn::make('inventoryItem.name')->label(__('inventory.fields.name')),
                TextColumn::make('quantity')->label(__('invoices.fields.quantity')),
            ]);
    }
}
