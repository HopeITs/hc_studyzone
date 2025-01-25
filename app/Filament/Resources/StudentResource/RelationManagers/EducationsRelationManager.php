<?php

namespace App\Filament\Resources\StudentResource\RelationManagers;

use App\Models\Education;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Table;

class EducationsRelationManager extends RelationManager
{
    protected static string $relationship = 'studentEducations';

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('education_id')
                ->label('Educations')
                ->options(Education::all()->pluck('name', 'id'))
                ->preload()
                ->searchable(),

            Forms\Components\Select::make('level')
                ->label('Education Level')
                ->options(__('general.edu-levels'))
                ->required(),

            Forms\Components\Select::make('type')
                ->label('Education Type')
                ->options(__('general.edu-types'))
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('education.name')->label('Education')->sortable(),
            Tables\Columns\TextColumn::make('level')->label('Level')
                ->formatStateUsing(fn (string $state): string => __("general.edu-degrees.{$state}"))
                ->sortable(),
            Tables\Columns\TextColumn::make('type')->label('Type')
                ->formatStateUsing(fn (string $state): string => __("general.edu-degrees.{$state}"))
                ->sortable(),
        ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
