<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncHelper
{
    /**
     * Sync a given table between local and online databases.
     *
     * @param string $table The table name.
     */
    public static function syncTable(string $table): void
    {
        try {
            DB::transaction(function () use ($table) {
                // Step 1: Sync Local to Online
                self::syncLocalToOnline($table);

                // Step 2: Sync Online to Local
                self::syncOnlineToLocal($table);
            });

            Log::info("Synchronization for table {$table} completed.");
        } catch (\Exception $e) {
            Log::error("Sync error for {$table}: " . $e->getMessage());
        }
    }

    /**
     * Upload local data to the online server.
     */
    private static function syncLocalToOnline(string $table): void
    {
        $localData = DB::table($table)
            ->where(function ($query) {
                $query->whereNull('synced_at') // Never synced
                ->orWhereColumn('updated_at', '>', 'synced_at'); // Modified after last sync
            })
            ->get();

        if ($localData->isEmpty()) {
            Log::info("No new data to sync from local to online for {$table}.");
            return;
        }

        foreach ($localData as $row) {
            $data = (array) $row;

            // Merge data on online server
            DB::connection('online_mysql')->table($table)->updateOrInsert(
                ['id' => $data['id']], // Unique identifier
                $data
            );

            // update synced_at on local
            $updated = DB::connection('mysql')->table($table)
                ->find('id', $data['id'])
                ->update(['synced_at' => now()]);

            dd($updated); // Will return 1 if successful, 0 if no row was updated

        }

        Log::info("Uploaded " . count($localData) . " records to online server for {$table}.");
    }

    /**
     * Download online data to the local server.
     */
    private static function syncOnlineToLocal(string $table): void
    {
        $onlineData = DB::connection('online_mysql')->table($table)
            ->where(function ($query) {
                $query->whereNull('synced_at') // Never synced
                ->orWhereColumn('updated_at', '>', 'synced_at'); // Modified after last sync
            })
            ->get();

        if ($onlineData->isEmpty()) {
            Log::info("No new data to sync from online to local for {$table}.");
            return;
        }

        foreach ($onlineData as $row) {
            $data = (array) $row;

            // Merge data locally
            DB::table($table)->updateOrInsert(
                ['id' => $data['id']], // Unique identifier
                $data
            );

            // Mark as synced online
            DB::connection('online_mysql')->table($table)
                ->where('id', $data['id'])
                ->update(['synced_at' => now()]);
        }

        Log::info("Downloaded " . count($onlineData) . " records to local server for {$table}.");
    }
}
