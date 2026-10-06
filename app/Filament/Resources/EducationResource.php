<?php

namespace App\Filament\Resources;

use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\EducationResource\Pages\ListEducation;
use App\Enums\Roles;
use App\Filament\Resources\EducationResource\Pages;
use App\Filament\Resources\EducationResource\RelationManagers;
use App\Models\Education;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Forms\Components\TranslatableFields;

class EducationResource extends Resource
{
    use Translatable;

    protected static ?string $model = Education::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-academic-cap';
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

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TranslatableFields::make(TextInput::make('name')->required()), Select::make('degree')->options(__('general.edu-degrees'))->required()]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([TextColumn::make('name')->sortable()->searchable(), TextColumn::make('degree')->formatStateUsing(fn($state): string => __('general.edu-degrees.' . (is_string($state) ? $state : $state->value)))->sortable()])
            ->filters([SelectFilter::make('degree')->label(__('general.labels.filter_by_degree'))->options(__('general.edu-degrees'))->searchable()])
            ->recordActions([ViewAction::make(), EditAction::make()->hidden(fn() => auth()->user()->role === Roles::Observer()), DeleteAction::make()->hidden(fn() => auth()->user()->role === Roles::Observer())])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()->hidden(fn() => auth()->user()->role === Roles::Observer())])->hidden(fn() => auth()->user()->role === Roles::Observer())]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEducation::route('/'),
        ];
    }
}
