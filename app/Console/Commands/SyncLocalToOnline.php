<?php

namespace App\Console\Commands;

use App\Helpers\SyncHelper;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class SyncLocalToOnline extends Command
{
    protected $signature = 'sync:local-to-online';
    protected $description = 'sync local to online database using async jobs';

    public function handle(): void
    {
        $this->info('🔁 Starting synchronization...');

        if (!SyncHelper::isInternetAvailable()) {
            $this->error('❌ No internet connection available.');
            return;
        }

        SyncHelper::syncLocalToOnline();

        $this->info('✅ Synchronization completed.');
    }
}

