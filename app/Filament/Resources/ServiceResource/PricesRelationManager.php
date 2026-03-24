<?php

namespace App\Filament\Resources\ServiceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PricesRelationManager extends RelationManager
{
    protected static string $relationship = 'prices';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('price')
                    ->label(__('general.labels.price'))
                    ->numeric()
                    ->suffix('SYP')
                    ->required(),

                Forms\Components\Toggle::make('is_default')
                    ->label(__('general.labels.is_active_price'))
                    ->default(false)
                    ->live()
                    ->afterStateUpdated(function (Forms\Set $set, $state) {
                        if ($state) {
                            $set('end_at', null);
                        }
                    }),

                Forms\Components\DateTimePicker::make('start_at')
                    ->label(__('general.labels.start_date'))
                    ->required()
                    ->default(now()),

                Forms\Components\DateTimePicker::make('end_at')
                    ->label(__('general.labels.end_date'))
                    ->disabled(fn (Forms\Get $get) => $get('is_default'))
                    ->dehydrated(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('price')
            ->columns([
                Tables\Columns\TextColumn::make('price')
                    ->label(__('general.labels.price'))
                    ->suffix(' SYP'),

                Tables\Columns\IconColumn::make('is_default')
                    ->label(__('general.labels.status'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),

                Tables\Columns\TextColumn::make('start_at')
                    ->label(__('general.labels.start_date'))
                    ->dateTime('Y-m-d H:i'),

                Tables\Columns\TextColumn::make('end_at')
                    ->label(__('general.labels.end_date'))
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('-'),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}