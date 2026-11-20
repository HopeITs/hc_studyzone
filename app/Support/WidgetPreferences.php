<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

final class WidgetPreferences
{
    public static function get(string $page): array
    {
        return Cache::get(self::key($page), []);
    }

    public static function save(string $page, array $preferences): void
    {
        Cache::forever(self::key($page), array_values($preferences));
    }

    private static function key(string $page): string
    {
        return 'widget-preferences:'.auth()->id().':'.$page;
    }
}
