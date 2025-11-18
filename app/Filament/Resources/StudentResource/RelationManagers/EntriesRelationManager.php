<?php

namespace App\Filament\Resources\StudentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Hidden;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Actions;
use Illuminate\Support\Facades\Auth;

class EntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'entries';
    protected static ?string $title = 'الدخوليات';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('student_id')->default(fn($livewire) => $livewire->ownerRecord->id),
            Forms\Components\Hidden::make('center_id')->default(fn() => Auth::user()->center_id)->required(),
            Forms\Components\Hidden::make('user_id')->default(fn() => Auth::id())->required(),

            Forms\Components\Select::make('entry_type')
                ->label(__('general.labels.entry_type'))
                ->options(__('general.entry-types'))
                ->required(),

            Forms\Components\DateTimePicker::make('entry_time')
                ->label(__('general.labels.entry_time'))
                ->default(now())
                ->required(),

            Forms\Components\Fieldset::make('خدمات')
                ->schema([
                    Forms\Components\Checkbox::make('use_printer')->label('خدمة طباعة')->default(false),
                    Forms\Components\Checkbox::make('use_mecrator')->label('خدمة ميكراتور')->default(false),
                    Forms\Components\Checkbox::make('use_drawer')->label('خدمة مرسم')->default(false),
                    Forms\Components\Checkbox::make('use_plotter')->label('خدمة بلوتر')->default(false),
                ])
                ->columns(4),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('entry_time')
            ->columns([
                TextColumn::make('entry_type')
                    ->label('النوع')
                    ->formatStateUsing(fn($state) => __('general.entry-types.' . $state))
                    ->sortable(),
                TextColumn::make('center.name')->label('المركز')->sortable(),
                TextColumn::make('entry_time')->label('التاريخ')->dateTime('Y-m-d H:i')->sortable(),
                CheckboxColumn::make('use_printer')->label('طباعة')->sortable(),
                CheckboxColumn::make('use_mecrator')->label('ميكراتور')->sortable(),
                CheckboxColumn::make('use_drawer')->label('مرسم')->sortable(),
                CheckboxColumn::make('use_plotter')->label('بلوتر')->sortable(),
            ])
            ->headerActions([
                Actions\CreateAction::make(),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
