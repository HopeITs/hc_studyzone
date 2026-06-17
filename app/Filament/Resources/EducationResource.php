<?php

namespace App\Filament\Resources;

use App\Enums\Roles;
use App\Filament\Resources\EducationResource\Pages;
use App\Filament\Resources\EducationResource\RelationManagers;
use App\Models\Education;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Mvenghaus\FilamentPluginTranslatableInline\Forms\Components\TranslatableContainer;

class EducationResource extends Resource
{
    use Translatable;

    protected static ?string $model = Education::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('general.labels.education_plural');
    }

    public static function getModelLabel(): string
    {
        return __('general.labels.education_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.labels.education_plural');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->role === Roles::GeneralAdmin() && auth()->user()->role !== Roles::Observer();
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->role === Roles::GeneralAdmin() || auth()->user()->role === Roles::Observer();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([TranslatableContainer::make(Forms\Components\TextInput::make('name')->required()), Forms\Components\Select::make('degree')->options(__('general.edu-degrees'))->required()]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([Tables\Columns\TextColumn::make('name')->sortable()->searchable(), Tables\Columns\TextColumn::make('degree')->formatStateUsing(fn($state): string => __('general.edu-degrees.' . (is_string($state) ? $state : $state->value)))->sortable()])
            ->filters([SelectFilter::make('degree')->label(__('general.labels.filter_by_degree'))->options(__('general.edu-degrees'))->searchable()])
            ->actions([ViewAction::make(), EditAction::make()->hidden(fn() => auth()->user()->role === Roles::Observer()), DeleteAction::make()->hidden(fn() => auth()->user()->role === Roles::Observer())])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()->hidden(fn() => auth()->user()->role === Roles::Observer())])->hidden(fn() => auth()->user()->role === Roles::Observer())]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEducation::route('/'),
        ];
    }
}
