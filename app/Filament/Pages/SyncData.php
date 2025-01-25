<?php
namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Filament\Notifications\Notification;

class SyncData extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static string $view = 'filament.pages.sync-data';

    public function syncData(): void
    {
        Artisan::call('sync:data');

        Notification::make()
            ->title('Sync Completed')
            ->success()
            ->body('Local data has been successfully synchronized with the online server.')
            ->send();
    }
}

