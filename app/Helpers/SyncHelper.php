<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

class SyncHelper
{
    public static function sync(): void
    {
        Log::info("🔁 Starting sync loop");

        ini_set('max_execution_time', 0); // No timeout
        ini_set('memory_limit', '1024M'); // Adjust if needed

        try {
            if (!self::isInternetAvailable()) {
                Log::warning("🚫 No internet connection");
                return;
            }

            $syncedLocal = self::syncLocalToOnline();
            // $syncedRemote = self::syncOnlineToLocal(); // Optional

            Log::info("✅ Sync completed. Local records synced: $syncedLocal");

        } catch (\Throwable $e) {
            Log::error("❌ Sync error: " . $e->getMessage());
        }
    }

    public static function syncLocalToOnline(): int
    {


        $syncedCount = 0;


            Activity::whereNull('synced_at')
                ->orderBy('event')
                ->orderBy('created_at')
                ->chunk(50, function ($activities) use (&$syncedCount) {
                    foreach ($activities as $activity) {
                        try {
                            $modelClass = $activity->subject_type;
                            $attributes = $activity->properties['attributes'] ?? [];
                            $query = $modelClass::on('online_mysql');

                            match ($activity->event) {
                                'created' => $query->create($attributes),
                                'updated' => $query->find($activity->subject_id)?->update($attributes),
                                'deleted' => $query->find($activity->subject_id)?->delete(),
                                default => Log::warning("Unknown event type: {$activity->event}"),
                            };

                            $activity->update(['synced_at' => now()]);
                            $syncedCount++;

                        } catch (\Throwable $e) {
                            Log::error("❌ Failed to sync [{$modelClass}] ID {$activity->id}: " . $e->getMessage());

                            if (!self::isInternetAvailable()) {
                                Log::warning("🚫 Lost internet connection. Halting sync...");
                                return false;
                            }
                        }
                    }
                });


        return $syncedCount;
    }



    public static function syncOnlineToLocal(): int
    {
        // Optional: You can implement fetching changes from the remote DB.
        return 0;
    }

    public static function isInternetAvailable(): bool
    {
        try {
            return Http::timeout(3)->get('https://www.google.com')->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
