<?php

namespace App\Filament\Resources;

use App\Enums\Roles;
use App\Filament\Resources\LockedMonthResource\Pages;
use App\Models\LockedMonth;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

class LockedMonthResource extends Resource
{
    protected static ?string $model = LockedMonth::class;

    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';

    protected static ?string $navigationGroup = 'إدارة النظام';

    protected static ?int $navigationSort = 60;

    protected static ?string $slug = 'month-locks';

    public static function getNavigationLabel(): string
    {
        return __('general.labels.month_locks');
    }

    public static function getModelLabel(): string
    {
        return __('general.labels.month_locks');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.labels.month_locks');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->role === Roles::GeneralAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLockedMonths::route('/'),
        ];
    }
}
