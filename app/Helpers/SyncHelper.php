<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SyncHelper
{


    private static array $foreignKeyDependencies = [
        'students' => [
            'street_id' => 'streets',
        ],
        'student_educations' => [
            'education_id' => 'educations',
            'student_id' => 'students',
        ],
        'student_entries' => [
            'user_id' => 'users',
            'student_id' => 'students',
            'center_id' => 'centers',
        ],
    ];

    public static function syncTable(string $table): void
    {
        Log::info("🔁 Starting sync loop for table: {$table}");

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


            $onlineDb = DB::connection('online_mysql')->table($table);

            try {
                $onlineDb->updateOrInsert(['id' => $data['id']], $filteredData);

                DB::table($table)->where('id', $data['id'])->update(['synced_at' => $data['synced_at']]);
            } catch (\Exception $e) {
                Log::error("❌ Failed to upload record ID {$data['id']} for {$table}: " . $e->getMessage());
            }
        }

        Log::info("✅ Uploaded " . count($localData) . " records to online for {$table}");

        return DB::table($table)
            ->whereNull('synced_at')
            ->orWhereColumn('updated_at', '>', 'synced_at')
            ->count();
    }

    public static function syncOnlineToLocal(string $table): int
    {
        Log::info("🔄 Syncing online to local: {$table}");

        $query = DB::connection('online_mysql')->table($table)->whereNull('synced_at');
        if (Schema::connection('online_mysql')->hasColumn($table, 'updated_at')) {
            $query->orWhereColumn('updated_at', '>', 'synced_at');
        }

        $onlineData = $query->get();
        $localColumns = Schema::getColumnListing($table);

        $syncedCount = 0;

        foreach ($onlineData as $row) {
            $data = (array)$row;
            $data['synced_at'] = now();
            $filteredData = array_intersect_key($data, array_flip($localColumns));

            // Resolve foreign keys
            if (isset(self::$foreignKeyDependencies[$table])) {
                foreach (self::$foreignKeyDependencies[$table] as $foreignKey => $relatedTable) {
                    if (
                        isset($filteredData[$foreignKey]) &&
                        !DB::table($relatedTable)->where('id', $filteredData[$foreignKey])->exists()
                    ) {
                        Log::warning("⚠️ FK {$foreignKey} with value {$filteredData[$foreignKey]} not found in {$relatedTable}, skipping record ID {$data['id']}.");
                        continue 2; // skip this record entirely
                    }
                }
            }


            $localDb = DB::connection('mysql')->table($table);

            try {

                $localDb->updateOrInsert(['id' => $data['id']], $filteredData);


                // Mark as synced
                $onlineDb = DB::connection('online_mysql')->table($table);

                $onlineDb->where('id', $data['id'])->update(['synced_at' => $data['synced_at']]);


                $syncedCount++;
            } catch (\Exception $e) {
                Log::error("❌ Failed to sync record ID {$data['id']} for {$table}: " . $e->getMessage());
            }
        }

        Log::info("✅ Downloaded {$syncedCount} records to local for {$table}");

        return DB::connection('online_mysql')->table($table)
            ->whereNull('synced_at')
            ->orWhereColumn('updated_at', '>', 'synced_at')
            ->count();
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
