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

        if (!SyncHelper::isInternetAvailable()) {
            $this->error('No internet connection is available...');

            return;
        }
        $this->info('Starting data synchronization...');

        $tables = [
            'cities' , 'centers' , 'streets' , 'users' , 'educations',
            'students' , 'student_educations' , 'student_entries'
        ];
        $this->info('Starting bidirectional sync...');
        $this->output->progressStart(count($tables));
        foreach ($tables as $table) {
            $this->info("➡️ Syncing table: {$table}");
            SyncHelper::syncLocalToOnline($table);
            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

//        DB::transaction(function () use ($tables) {
//            foreach ($tables as $table) {
//                SyncHelper::syncTable($table);
//            }
//        });

        $this->info('Sync completed.');


    }
}

