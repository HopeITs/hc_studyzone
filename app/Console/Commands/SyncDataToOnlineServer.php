<?php

namespace App\Console\Commands;

use App\Helpers\SyncHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class SyncDataToOnlineServer extends Command
{
    protected $signature = 'sync:data';
    protected $description = 'Bidirectional sync between local and online database using async jobs';

    public function handle(): void
    {
        $this->info('Starting data synchronization...');

        $tables = [
            'cities' , 'centers' , 'streets' , 'users' , 'educations',
            'permissions' , 'roles' , 'model_has_permissions' , 'model_has_roles' , 'role_has_permissions',
            'students' , 'student_educations' , 'student_entries'
        ];
        $this->info('Starting bidirectional sync...');
        $this->output->progressStart(count($tables));
        foreach ($tables as $table) {
            $this->info("➡️ Syncing table: {$table}");
            SyncHelper::syncTable($table);
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

