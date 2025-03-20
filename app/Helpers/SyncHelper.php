<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SyncHelper
{
    /**
     * Sync all specified tables between local and online databases.
     */
    public static function syncAllTables(array $tables): void
    {
        foreach ($tables as $table) {
            self::syncTable($table);
        }
    }

    /**
     * Sync a single table.
     */
    public static function syncTable(string $table): void
    {
        Log::info("✅ Sync Start for table: {$table}");
        try {

            DB::transaction(function () use ($table) {
                self::syncLocalToOnline($table);
                self::syncOnlineToLocal($table);
            });

            Log::info("✅ Sync completed for table: {$table}");
        } catch (\Exception $e) {
            Log::error("❌ Sync error for {$table}: " . $e->getMessage());
        }
    }

    /**
     * Upload local data to the online server.
     */
    private static function syncLocalToOnline(string $table): void
    {
        Log::info("🔄 Syncing local to online: {$table}");

        $query = DB::table($table)->whereNull('synced_at');

        if (Schema::hasColumn($table, 'updated_at')) {
            $query->orWhereColumn('updated_at', '>', 'synced_at');
        }

        $localData = $query->get();

        foreach ($localData as $row) {
            $data = (array)$row;
            $data['synced_at'] = now();

            $onlineDb = DB::connection('online_mysql')->table($table);
            $existingRecord = $onlineDb->where('id', $data['id'])->first();

            if ($existingRecord) {
                $onlineDb->where('id', $data['id'])->update($data);
            } else {
                $onlineDb->insert($data);
            }

            DB::table($table)->where('id', $data['id'])->update(['synced_at' => $data['synced_at']]);
        }

        Log::info("✅ Uploaded " . count($localData) . " records to online for {$table}");
    }

    /**
     * Download online data to the local server.
     */
    private static function syncOnlineToLocal(string $table): void
    {
        Log::info("🔄 Syncing online to local: {$table}");

        $query = DB::connection('online_mysql')->table($table)->whereNull('synced_at');

        if (Schema::hasColumn($table, 'updated_at')) {
            $query->orWhereColumn('updated_at', '>', 'synced_at');
        }

        $onlineData = $query->get();

        foreach ($onlineData as $row) {
            $data = (array)$row;

            DB::table($table)->updateOrInsert(['id' => $data['id']], $data);

            DB::connection('online_mysql')->table($table)
                ->where('id', $data['id'])
                ->update(['synced_at' => now()]);
        }

        Log::info("✅ Downloaded " . count($onlineData) . " records to local for {$table}");
    }
}
