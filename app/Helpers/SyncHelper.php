<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SyncHelper
{



    public static function syncTable(string $table): void
    {
        Log::info("🔁 Starting sync loop for table: {$table}");
        ini_set('max_execution_time', 0); // unlimited
        ini_set('memory_limit', '1G');    // or more depending on server
        try {

            $onlineUnsynced = self::syncOnlineToLocal($table);
            $localUnsynced = self::syncLocalToOnline($table);

            Log::info("✅ Fully synced table: {$table}");

        } catch (\Exception $e) {
            Log::error("❌ Sync error for {$table}: " . $e->getMessage());
        }
    }

    public static function syncLocalToOnline(string $table): int
    {
        $syncedCount = 0;
        $fetchLimit = 2000;
        $chunkSize = 200;

        Log::info("🔄 Starting sync: local ➜ online for [{$table}] (max: {$fetchLimit}, chunks: {$chunkSize})");

        // Get columns that exist in the online DB
        $onlineColumns = Schema::connection('online_mysql')->getColumnListing($table);

        // Step 1: Fetch the top 2000 recently updated or unsynced rows from local DB
        $rows = DB::table($table)
            ->whereNull('synced_at')
            ->orWhereColumn('updated_at', '>', 'synced_at')
            ->orderByDesc('updated_at')
            ->limit($fetchLimit)
            ->get();

        if ($rows->isEmpty()) {
            Log::info("📭 No unsynced records found for [{$table}]");
            return 0;
        }

        // Step 2: Chunk the in-memory collection into 200-item chunks
        $rows->chunk($chunkSize)->each(function ($chunk) use (&$syncedCount, $table, $onlineColumns) {
            foreach ($chunk as $row) {
                $data = (array)$row;
                $data['synced_at'] = now();

                $filteredData = array_intersect_key($data, array_flip($onlineColumns));

                try {
                    DB::connection('online_mysql')
                        ->table($table)
                        ->updateOrInsert(['id' => $data['id']], $filteredData);

                    DB::table($table)
                        ->where('id', $data['id'])
                        ->update(['synced_at' => $data['synced_at']]);

                    $syncedCount++;
                } catch (\Throwable $e) {
                    Log::error("❌ Failed to sync record [{$data['id']}] to online for [{$table}]: {$e->getMessage()}");
                }
            }
        });

        Log::info("✅ Completed sync: {$syncedCount} records synced from local to online for [{$table}]");

        return $syncedCount;
    }



    public static function syncOnlineToLocal(string $table): int
    {
        $syncedCount = 0;
        $limit = 1000;
        $chunkSize = 200;
        Log::info("🔄 Starting sync: online ➜ local for [{$table}] (limit: {$limit} rows)");

        // Get local table columns
        $localColumns = Schema::getColumnListing($table);

        // Step 1: Fetch top 2000 recently updated/unsynced rows from online
        $onlineRows = DB::connection('online_mysql')
            ->table($table)
            ->whereNull('synced_at')
            ->orWhereColumn('updated_at', '>', 'synced_at')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        if ($onlineRows->isEmpty()) {
            Log::info("📭 No unsynced records found for [{$table}]");
            return 0;
        }

        $onlineRows->chunk($chunkSize)->each(function ($chunk) use (&$syncedCount, $table, $localColumns) {
            foreach ($chunk as $row) {
                $data = (array)$row;
                // Add synced_at timestamp
                $data['synced_at'] = now();
                // Filter only valid local columns
                $filteredData = array_intersect_key($data, array_flip($localColumns));


                try {
                    // Step 3: Sync to local database
                    DB::table($table)->updateOrInsert(
                        ['id' => $data['id']],
                        $filteredData
                    );

                    // Step 4: Mark record as synced in online DB
                    DB::connection('online_mysql')
                        ->table($table)
                        ->where('id', $data['id'])
                        ->update(['synced_at' => $data['synced_at']]);

                    $syncedCount++;
                } catch (\Throwable $e) {
                    Log::error("❌ Error syncing record [{$data['id']}] in [{$table}]: {$e->getMessage()}");
                }
            }
        });
        Log::info("✅ Completed sync: {$syncedCount} records synced from online to local for [{$table}]");

        return $syncedCount;
    }


    public static function isInternetAvailable(): bool
    {
        try {
            $response = Http::timeout(3)->get('https://www.google.com');
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
