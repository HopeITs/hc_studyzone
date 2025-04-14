<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SyncHelper
{
    /**
     * Pivot table definitions (no id, use composite keys).
     */
    private static array $pivotKeys = [
        'model_has_roles' => ['role_id', 'model_type', 'model_id'],
        'role_has_permissions' => ['role_id', 'permission_id'],
        'model_has_permissions' => ['permission_id', 'model_type', 'model_id'],
    ];

    /**
     * Sync all specified tables.
     */
    public static function syncAllTables(array $tables): void
    {
        foreach ($tables as $table) {
            self::syncTable($table);
        }
    }

    /**
     * Sync a single table (local ↔ online).
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
        $onlineColumns = Schema::connection('online_mysql')->getColumnListing($table);

        foreach ($localData as $row) {
            $data = (array)$row;
            $data['synced_at'] = now();
            $filteredData = array_intersect_key($data, array_flip($onlineColumns));

            $pivotKeys = self::$pivotKeys[$table] ?? null;
            $onlineDb = DB::connection('online_mysql')->table($table);

            if ($pivotKeys) {
                $matchKeys = array_intersect_key($filteredData, array_flip($pivotKeys));
                $onlineDb->updateOrInsert($matchKeys, $filteredData);
            } else {
                $onlineDb->updateOrInsert(['id' => $data['id']], $filteredData);
            }

            // Update local synced_at
            $localDb = DB::table($table);
            if ($pivotKeys) {
                $query = $localDb;
                foreach ($pivotKeys as $key) {
                    $query = $query->where($key, $data[$key]);
                }
                $query->update(['synced_at' => $data['synced_at']]);
            } else {
                $localDb->where('id', $data['id'])->update(['synced_at' => $data['synced_at']]);
            }
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
        $localColumns = Schema::getColumnListing($table);

        foreach ($onlineData as $row) {
            $data = (array)$row;
            $data['synced_at'] = now();
            $filteredData = array_intersect_key($data, array_flip($localColumns));

            $pivotKeys = self::$pivotKeys[$table] ?? null;
            $localDb = DB::table($table);

            if ($pivotKeys) {
                $matchKeys = array_intersect_key($filteredData, array_flip($pivotKeys));
                $localDb->updateOrInsert($matchKeys, $filteredData);
            } else {
                $localDb->updateOrInsert(['id' => $data['id']], $filteredData);
            }

            // Update remote synced_at
            $onlineDb = DB::connection('online_mysql')->table($table);
            if ($pivotKeys) {
                $query = $onlineDb;
                foreach ($pivotKeys as $key) {
                    $query = $query->where($key, $data[$key]);
                }
                $query->update(['synced_at' => $data['synced_at']]);
            } else {
                $onlineDb->where('id', $data['id'])->update(['synced_at' => $data['synced_at']]);
            }
        }

        Log::info("✅ Downloaded " . count($onlineData) . " records to local for {$table}");
    }
}
