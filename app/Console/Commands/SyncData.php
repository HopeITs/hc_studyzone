<?php

namespace App\Console\Commands;

use App\Helpers\SyncHelper;
use Illuminate\Console\Command;

class SyncData extends Command
{
    protected $signature = 'sync:data';
    protected $description = 'Synchronize local activity logs to the online database';

    public function handle(): void
    {
        $this->info('🔁 Starting synchronization...');

        if (!SyncHelper::isInternetAvailable()) {
            $this->error('❌ No internet connection available.');
            return;
        }

        SyncHelper::sync();

        $this->info('✅ Synchronization completed.');
    }
}
