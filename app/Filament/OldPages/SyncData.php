<?php

namespace App\Filament\OldPages;

use App\Helpers\SyncHelper;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;

class SyncData extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected string $view = 'filament.pages.sync-data';
    protected static ?string $title = 'مزامنة البيانات';
    public function syncData(): void
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

        Artisan::call('sync:data');

        Notification::make()
            ->title('✅ Sync Completed')
            ->success()
            ->body('Local data has been successfully synchronized with the online server.')
            ->send();
    }

    public function syncOnlineToLocal(): void
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

        Artisan::call('sync:online-to-local');

        Notification::make()
            ->title('✅ Sync Completed')
            ->success()
            ->body('Local data has been successfully synchronized with the online server.')
            ->send();
    }
    public function syncLocalToOnline(): void
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

        Artisan::call('sync:local-to-online');

        Notification::make()
            ->title('✅ Sync Completed')
            ->success()
            ->body('Local data has been successfully synchronized with the online server.')
            ->send();
    }

}
