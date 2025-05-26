<?php

namespace App\Helpers;

use DateTime;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;
use function Laravel\Prompts\password;

class SyncHelper
{
    // Define model sync priority (parents first)
    public array $modelOrder = [
        'App\Models\City',
        'App\Models\Street',
        'App\Models\Center',
        'App\Models\Education',
        'App\Models\User',
        'App\Models\Student',
        'App\Models\StudentEducation',
        'App\Models\StudentEntry',
        // Add other models in correct dependency order
    ];

    public static function sync(): void
    {
        Log::info("🔁 Starting sync loop");

        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '1024M');

        try {
            if (!self::isInternetAvailable()) {
                Log::warning("🚫 No internet connection detected. Sync aborted.");
                return;
            }
            Log::info("🌐 Internet connection verified");

            Log::info("🟡 Starting syncOnlineToLocal()");
            $syncedRemote = self::syncOnlineToLocal();
            Log::info("✅ Online to local sync completed. Records synced: $syncedRemote");

            Log::info("🟡 Starting syncLocalToOnline()");
            $syncedLocal = self::syncLocalToOnline();
            Log::info("✅ Local to online sync completed. Records synced: $syncedLocal");

        } catch (\Throwable $e) {
            Log::error("❌ Sync error: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        }
    }

    public static function syncOnlineToLocal(): int
    {

        $lastSync = Activity::max('synced_at') ?? now()->subYear();
        $syncedCount = 0;

        Activity::on('online_mysql')
            ->where('created_at', '>', $lastSync)
            ->where('log_name', '!=', config('activitylog.default_log_name'))
            ->orderBy('created_at')
            ->chunk(50, function ($activities) use (&$syncedCount) {
                $grouped = $activities->groupBy('subject_type');

                $helper = new self();
                $modelOrder = $helper->modelOrder;

                $allModels = collect($grouped->keys())->all();
                $orderedModels = array_merge(
                    $modelOrder,
                    array_diff($allModels, $modelOrder)
                );

                dd($grouped, $modelOrder,$orderedModels);
                foreach ($orderedModels as $modelClass) {
                    if (!isset($grouped[$modelClass])) {
                        continue;
                    }

                    foreach ($grouped[$modelClass] as $activity) {

                        try {
                            $synced_at = now();
                            $attributes = $activity->properties['attributes'] ?? [];
                            $attributes['synced_at'] = $synced_at;
                            $query = $modelClass::on('mysql');

                            if ($activity->event === 'created' || $activity->event === 'updated') {
                                $attributes = Arr::except($attributes, ['created_at', 'updated_at']);
                                $obj = $modelClass::upsert($attributes, [$activity->subject_id],$attributes);

                            }


                            if ($activity->event === 'deleted') {
                                $obj = $modelClass::find($activity->subject_id)?->delete();
                            }




                            $localActivity = Activity::on('mysql')->latest()->first();
                            $localActivity->update(['synced_at' => $synced_at]);
                            dd($obj , $activity ,$localActivity);
                            $syncedCount++;


                        } catch (\Throwable $e) {
                            Log::error("❌ Failed syncing [{$modelClass}] Activity ID {$activity->id}: {$e->getMessage()}");

                            if (!self::isInternetAvailable()) {
                                Log::warning("🚫 Internet lost. Halting sync.");
                                return false;
                            }
                        }
                    }
                }
            });

        return $syncedCount;
    }

    public static function syncLocalToOnline(): int
    {
        $syncedCount = 0;

        Activity::whereNull('synced_at')
            ->where('log_name', config('activitylog.default_log_name'))
            ->orderBy('created_at')
            ->chunk(50, function ($activities) use (&$syncedCount) {
                $grouped = $activities->groupBy('subject_type');

                $helper = new self();
                $modelOrder = $helper->modelOrder;

                $allModels = collect($grouped->keys())->all();
                $orderedModels = array_merge(
                    $modelOrder,
                    array_diff($allModels, $modelOrder)
                );

                foreach ($orderedModels as $modelClass) {
                    if (!isset($grouped[$modelClass])) {
                        continue;
                    }

                    foreach ($grouped[$modelClass] as $activity) {
                        try {
                            $attributes = $activity->properties['attributes'] ?? [];
                            $query = $modelClass::on('online_mysql');

                            match ($activity->event) {
                                'created' => fn() => self::createAndLog($modelClass, $attributes),
                                'updated' => fn() => self::updateAndLog($query, $activity, $attributes),
                                'deleted' => fn() => self::deleteAndLog($query, $activity),
                                default => Log::warning("⚠️ Unknown event type: {$activity->event} (ID: {$activity->id})"),
                            };

                            $activity->update(['synced_at' => now()]);
                            $syncedCount++;

                        } catch (\Throwable $e) {
                            Log::error("❌ Failed to sync local [{$modelClass}] ID {$activity->id}: " . $e->getMessage());

                            if (!self::isInternetAvailable()) {
                                Log::warning("🚫 Internet lost during sync. Stopping...");
                                return false;
                            }
                        }
                    }
                }
            });

        return $syncedCount;
    }

    protected static function createAndLog(string $modelClass, array $attributes): void
    {
        $model = new $modelClass($attributes);
        $model->setConnection('online_mysql');

        if (method_exists($model, 'disableLogging')) {
            $model->disableLogging();
        }

        $model->save();

        activity(config('activitylog.default_log_name'))
            ->performedOn($model)
            ->withProperties(['attributes' => $attributes])
            ->log('created');
    }

    protected static function updateAndLog($query, $activity, array $attributes): void
    {
        $model = $query->find($activity->subject_id);
        if ($model) {
            if (method_exists($model, 'disableLogging')) {
                $model->disableLogging();
            }

            $model->update($attributes);

            activity(config('activitylog.default_log_name'))
                ->performedOn($model)
                ->withProperties(['attributes' => $attributes])
                ->log('updated');
        }
    }

    protected static function deleteAndLog($query, $activity): void
    {
        $model = $query->find($activity->subject_id);
        if ($model) {
            if (method_exists($model, 'disableLogging')) {
                $model->disableLogging();
            }

            $model->delete();

            activity(config('activitylog.default_log_name'))
                ->performedOn($model)
                ->log('deleted');
        }
    }

    public static function isInternetAvailable(): bool
    {
        try {
            return Http::timeout(3)->get('http://example.com')->successful();
        } catch (\Throwable $e) {
            Log::warning("🌐 Internet check failed: " . $e->getMessage());
            return false;
        }
    }
}
