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
                ->label('QR Scan')
                ->icon('heroicon-o-qr-code')
                ->modalHeading('Scan QR Code')
                ->modalContent(fn() => view('filament.qr-code-scanner'))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')]);
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
