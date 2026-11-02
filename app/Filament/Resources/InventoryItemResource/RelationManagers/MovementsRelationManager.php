<?php

namespace App\Filament\Resources\InventoryItemResource\RelationManagers;

use App\Enums\StockMovementType;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('inventory.movements.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'invoice']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('inventory.fields.created_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                TextColumn::make('type')
                    ->label(__('inventory.fields.type'))
                    ->formatStateUsing(fn (StockMovementType $state) => __('inventory.movement_types.'.$state->value))
                    ->badge()
                    ->color(fn (StockMovementType $state) => match ($state) {
                        StockMovementType::Purchase => 'success',
                        StockMovementType::Sale => 'info',
                        StockMovementType::Adjustment => 'warning',
                        StockMovementType::TeamAllocation => 'gray',
                    }),

                TextColumn::make('quantity')
                    ->label(__('inventory.fields.quantity'))
                    ->sortable(),

                TextColumn::make('cost')
                    ->label(__('inventory.fields.cost'))
                    ->suffix(' SYP')
                    ->placeholder('-'),

                TextColumn::make('user.name')
                    ->label(__('inventory.fields.user'))
                    ->placeholder('-'),

                TextColumn::make('invoice_id')
                    ->label(__('inventory.fields.invoice'))
                    ->formatStateUsing(fn ($state) => __('inventory.fields.invoice').' #'.$state)
                    ->placeholder('-'),

                TextColumn::make('note')
                    ->label(__('inventory.fields.note'))
                    ->limit(50)
                    ->placeholder('-'),
            ])
            // Read-only log: movements are created from item actions only.
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
