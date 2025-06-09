<?php

namespace App\Console\Commands;

use App\Helpers\SyncHelper;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class SyncOnlineToLocal extends Command
{
    protected $signature = 'sync:online-to-local';
    protected $description = 'Bidirectional sync between local and online database using async jobs';


    public function handle(): void
    {
        $this->info('🔁 Starting synchronization...');

        if (!SyncHelper::isInternetAvailable()) {
            $this->error('❌ No internet connection available.');
            return;
        }

        SyncHelper::syncOnlineToLocal();

        $this->info('✅ Synchronization completed.');
    }
}

