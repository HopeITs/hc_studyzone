<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Exports\InventoryItemsExport;
use App\Filament\Resources\InventoryItemResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListInventoryItems extends ListRecords
{
    protected static string $resource = InventoryItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('inventory.actions.export'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->action(function () {
                    return Excel::download(
                        new InventoryItemsExport($this->getFilteredTableQuery()),
                        'inventory-'.now()->format('Y-m-d').'.xlsx'
                    );
                }),

            CreateAction::make(),
        ];
    }
}
