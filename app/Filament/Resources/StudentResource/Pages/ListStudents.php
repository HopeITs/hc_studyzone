<?php

namespace App\Filament\Resources\StudentResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\StudentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListStudents extends ListRecords
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return array_merge(parent::getHeaderActions(), [CreateAction::make(),
            Action::make('scan_qr_code')
                ->label(__('general.labels.qr_scan'))
                ->icon('heroicon-o-qr-code')
                ->modalHeading(__('general.labels.scan_qr_code'))
                ->modalContent(fn() => view('filament.qr-code-scanner'))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('general.labels.close'))]);
    }

    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();
        $user = Auth::user();

        if ($user->role === 'city_admin') {
            return $query->where('city_id', $user->city_id);
        }

        return $query;
    }
}
