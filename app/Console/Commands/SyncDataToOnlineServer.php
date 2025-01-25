<?php

namespace App\Console\Commands;

use App\Helpers\SyncHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class SyncDataToOnlineServer extends Command
{
    protected $signature = 'sync:data';
    protected $description = 'Bidirectional sync between local and online database';

    public function handle(): void
    {
        $this->info('Starting data synchronization...');

        $tables = ['students', 'student_entries', 'educations', 'student_educations', 'centers', 'cities', 'users']; // Add all tables to sync

        $this->info('Starting bidirectional sync...');


        DB::transaction(function () use ($tables) {
            foreach ($tables as $table) {
                SyncHelper::syncTable($table);
            }
        });

        $this->info('Sync completed.');


    }
}

