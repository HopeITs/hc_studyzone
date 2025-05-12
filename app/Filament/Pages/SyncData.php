<?php

namespace App\Filament\Pages;

use App\Helpers\SyncHelper;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Filament\Notifications\Notification;

class SyncData extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static string $view = 'filament.pages.sync-data';

    public function syncData(): void
    {
        self::testInternetConnection();


        Artisan::call('sync:data');

        Notification::make()
            ->title('Sync Completed')
            ->success()
            ->body('Local data has been successfully synchronized with the online server.')
            ->send();
    }

    public function syncOnlineToLocal(): void
    {
        self::testInternetConnection();


        Artisan::call('sync:online-to-local');

        Notification::make()
            ->title('Sync Completed')
            ->success()
            ->body('local data has been successfully synchronized with the online server.')
            ->send();
    }

    public function syncLocalToOnline(): void
    {
        self::testInternetConnection();


        Artisan::call('sync:local-to-online');

        Notification::make()
            ->title('Sync Completed')
            ->success()
            ->body('online data has been successfully synchronized with the local server.')
            ->send();
    }

    private function testInternetConnection(): void
    {
        if (!SyncHelper::isInternetAvailable()) {
            Notification::make()
                ->title('❌ No Internet Connection')
                ->danger()
                ->body('Please check your internet connection and try again.')
                ->persistent()
                ->send();

            return;
        }
    }
}

