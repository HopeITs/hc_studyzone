<?php
namespace App\Filament\Resources;

use App\Models\Service;
use App\Models\ServiceCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;
    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required(),

                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name')
                            ->label('Unit')
                            ->required(),

                        Forms\Components\TextInput::make('current_price')
                            ->label('Current Price')
                            ->placeholder(fn ($record) => $record?->currentPrice?->price ?? 'No price set')
                            ->disabled()
                            ->dehydrated(false)
                            ->suffix('SYP'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Unit')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('currentPrice.price')
                    ->label('Price')
                    ->suffix(' SYP')
                    ->color('success')
                    ->placeholder('N/A'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ServiceResource\Pages\ListServices::route('/'),
            'create' => ServiceResource\Pages\CreateService::route('/create'),
            'edit' => ServiceResource\Pages\EditService::route('/{record}/edit'),
        ];
    }
}