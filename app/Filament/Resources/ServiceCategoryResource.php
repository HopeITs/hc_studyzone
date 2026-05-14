<?php

namespace App\Filament\Resources;

use App\Enums\Roles;
use App\Models\ServiceCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ServiceCategoryResource extends Resource
{
    protected static ?string $model = ServiceCategory::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';


    public static function getNavigationLabel(): string
    {
        return 'فئات الخدمات';
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        if ($user->role === Roles::GeneralAdmin()) {
            return parent::getEloquentQuery();
        }

        return parent::getEloquentQuery()->whereRaw('1 = 0');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('unit')
                    ->required()
                    ->options([
                        'day' => 'يوم',
                        'hour' => 'ساعة',
                        'paper' => 'ورقة',
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('unit')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        'day' => 'يوم',
                        'hour' => 'ساعة',
                        'paper' => 'ورقة',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ServiceCategoryResource\Pages\ListServiceCategories::route('/'),
            'create' => ServiceCategoryResource\Pages\CreateServiceCategory::route('/create'),
            'edit' => ServiceCategoryResource\Pages\EditServiceCategory::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return Auth::user()->role === Roles::GeneralAdmin();
    }

    public static function canEdit($record): bool
    {
        return Auth::user()->role === Roles::GeneralAdmin();
    }

    public static function canDelete($record): bool
    {
        return Auth::user()->role === Roles::GeneralAdmin();
    }
}
