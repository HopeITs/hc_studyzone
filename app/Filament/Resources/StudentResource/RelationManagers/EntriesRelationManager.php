<?php

namespace App\Filament\Resources\StudentResource\RelationManagers;

use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'entries';

    public function form(Form $form): Form
    {
        return $form->schema([


            Select::make('type')
                ->label('Type')
                ->options(__('general.entry-types'))
                ->required(),

        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('education.name')->label('Education')->sortable(),
            TextColumn::make('level')->label('Level')->sortable(),
            TextColumn::make('type')->label('Type')->sortable(),
        ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),

            ]);
    }
}
