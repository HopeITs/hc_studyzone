<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Display-only toggle between the new currency (stored) and the old one
 * (shown ×100 when the session flag is on). Storage is always new currency.
 */
class FinanceDisplay
{
    public const SESSION_KEY = 'finance.old_currency';

    public static function enabled(): bool
    {
        return (bool) session(self::SESSION_KEY, false);
    }

    public static function toggle(): void
    {
        session([self::SESSION_KEY => ! self::enabled()]);
    }

    public static function format(int $amount): string
    {
        return self::enabled()
            ? number_format($amount * 100)
            : number_format($amount);
    }
}
