<?php

namespace App\Filament\Resources\CashShiftResource\Pages;

use App\Enums\Roles;
use App\Filament\Resources\CashShiftResource;
use App\Models\CashShift;
use App\Models\Center;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListCashShifts extends ListRecords
{
    protected static string $resource = CashShiftResource::class;

    protected function getHeaderActions(): array
    {
        $user = Auth::user();
        $isGeneralAdmin = $user->role === Roles::GeneralAdmin();

        return [
            Action::make('open_shift')
                ->label(__('finance.actions.open_shift'))
                ->icon('heroicon-o-lock-open')
                ->color('primary')
                ->visible(fn () => CashShiftResource::userManagesShifts())
                ->schema([
                    Select::make('center_id')
                        ->label(__('finance.labels.center'))
                        ->options(fn () => Center::query()->where('is_active', true)->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->required($isGeneralAdmin)
                        ->visible($isGeneralAdmin),

                    TextInput::make('opening_cash')
                        ->label(__('finance.labels.opening_cash'))
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->suffix('SYP')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $user = Auth::user();

                    // Server-side role re-check — never trust visibility alone.
                    if (! CashShiftResource::userManagesShifts()) {
                        Notification::make()
                            ->title(__('finance.messages.not_allowed'))
                            ->danger()
                            ->send();

                        return;
                    }

                    // CenterAdmin is always forced to their own center.
                    $centerId = $user->role === Roles::GeneralAdmin()
                        ? (int) $data['center_id']
                        : (int) $user->center_id;

                    if (CashShift::openShiftForCenter($centerId)) {
                        Notification::make()
                            ->title(__('finance.messages.center_has_open_shift'))
                            ->danger()
                            ->send();

                        return;
                    }

                    CashShift::open($centerId, $user->id, (int) $data['opening_cash']);

                    Notification::make()
                        ->title(__('finance.messages.shift_opened'))
                        ->success()
                        ->send();
                }),
        ];
    }
}
