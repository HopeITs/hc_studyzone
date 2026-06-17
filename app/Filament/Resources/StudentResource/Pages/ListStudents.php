<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Filament\Resources\StudentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListStudents extends ListRecords
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return array_merge(parent::getHeaderActions(), [Actions\CreateAction::make(),
            Actions\Action::make('scan_qr_code')
                ->label(__('general.labels.qr_scan'))
                ->icon('heroicon-o-qr-code')
                ->modalHeading(__('general.labels.scan_qr_code'))
                ->modalContent(fn() => view('filament.qr-code-scanner'))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('general.labels.close'))]);
    }

    protected function getTableQuery(): ?\Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getTableQuery();
        $user = Auth::user();

        if ($user->role === 'city_admin') {
            return $query->where('city_id', $user->city_id);
        }

        return $query;
    }
}
