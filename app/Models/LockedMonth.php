<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class LockedMonth extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
        'unlocked_at' => 'datetime',
    ];

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function unlockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unlocked_by');
    }

    public function getPeriodLabelAttribute(): string
    {
        return Carbon::createFromDate($this->year, $this->month, 1)->translatedFormat('F Y');
    }

    /**
     * Whether the month containing $date is currently locked.
     * Cached briefly per request/process since this is checked on every
     * entry write (observers, form guards, UI disabled() callbacks).
     */
    public static function isLocked(CarbonInterface|string|null $date): bool
    {
        if (! $date) {
            return false;
        }

        $date = $date instanceof CarbonInterface ? $date : Carbon::parse($date);

        return in_array(
            $date->format('Y-n'),
            static::lockedPeriods(),
            true
        );
    }

    /**
     * "Y-n" keys of every currently locked month, memoized for the request.
     */
    protected static function lockedPeriods(): array
    {
        return Cache::remember('locked-months:active', 60, function () {
            return static::query()
                ->where('is_locked', true)
                ->get(['year', 'month'])
                ->map(fn (self $row) => "{$row->year}-{$row->month}")
                ->all();
        });
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('locked-months:active'));
        static::deleted(fn () => Cache::forget('locked-months:active'));
    }
}
