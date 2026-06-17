<?php

namespace App\Filament\Resources\StudentResource\RelationManagers;

use App\Models\Education;
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
                ->label(__('general.labels.educations'))
                ->options(Education::all()->pluck('name', 'id'))
                ->preload()
                ->searchable()
                ->required(),

            Select::make('level')->label(__('general.labels.education_level'))->options(__('general.edu-levels'))->required(),

            Select::make('type')->label(__('general.labels.education_type'))->options(__('general.edu-types'))->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('education.name')->label(__('general.labels.education'))->sortable(),

                Tables\Columns\TextColumn::make('education.degree')
                    ->label(__('general.labels.degree'))
                    ->formatStateUsing(function ($state, $record) {
                        $degree = $record->education->degree ?? null;

                        return $degree ? __('general.edu-degrees.' . $degree->value) : '-';
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('level')->label(__('general.labels.level'))->formatStateUsing(fn(string $state): string => __("general.edu-levels.{$state}"))->sortable(),

                Tables\Columns\TextColumn::make('type')->label(__('general.labels.type'))->formatStateUsing(fn(string $state): string => __("general.edu-types.{$state}"))->sortable(),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()])
            ->actions([EditAction::make(), DeleteAction::make()]);
    }
}
