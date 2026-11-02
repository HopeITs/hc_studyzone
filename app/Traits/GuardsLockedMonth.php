<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\LockedMonth;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Central locked-month guard for financial records.
 *
 * Hooks creating / updating / deleting and refuses the write when any
 * lock-relevant date of the record falls in a locked month. On update both
 * the new and the original dates are checked, so a record can neither be
 * edited inside a locked month nor moved into or out of one.
 *
 * Models override lockedMonthDateColumns() (or lockedMonthDates() for
 * derived dates, e.g. a parent's created_at). A null date falls back to
 * now(): a record being created "happens" now.
 *
 * The Excel importer bypasses the guard per model class via
 * Model::withoutLockedMonthGuard(fn () => ...).
 */
trait GuardsLockedMonth
{
    /** Escape hatch for historical imports; per using model class. */
    protected static bool $lockedMonthGuardBypassed = false;

    public static function withoutLockedMonthGuard(callable $callback): mixed
    {
        $previous = static::$lockedMonthGuardBypassed;
        static::$lockedMonthGuardBypassed = true;

        try {
            return $callback();
        } finally {
            static::$lockedMonthGuardBypassed = $previous;
        }
    }

    protected static function bootGuardsLockedMonth(): void
    {
        static::creating(fn (Model $model) => $model->assertLockedMonthsOpen(
            $model->lockedMonthDates(),
        ));

        static::updating(fn (Model $model) => $model->assertLockedMonthsOpen([
            ...$model->lockedMonthDates(),
            ...$model->lockedMonthDates(original: true),
        ]));

        static::deleting(fn (Model $model) => $model->assertLockedMonthsOpen(
            $model->lockedMonthDates(original: true),
        ));
    }

    /**
     * Attribute names whose month must be open for a write to go through.
     *
     * @return list<string>
     */
    protected function lockedMonthDateColumns(): array
    {
        return ['created_at'];
    }

    /**
     * The concrete lock-relevant dates (new or original values).
     *
     * @return array<int, CarbonInterface|string>
     */
    protected function lockedMonthDates(bool $original = false): array
    {
        return array_map(
            fn (string $column) => ($original
                ? $this->getOriginal($column)
                : $this->getAttribute($column)) ?? now(),
            $this->lockedMonthDateColumns(),
        );
    }

    /**
     * @param  array<int, CarbonInterface|string|null>  $dates
     *
     * @throws ValidationException when any date falls in a locked month.
     */
    protected function assertLockedMonthsOpen(array $dates): void
    {
        if (static::$lockedMonthGuardBypassed) {
            return;
        }

        foreach ($dates as $date) {
            if (LockedMonth::isLocked($date)) {
                throw ValidationException::withMessages([
                    'month' => __('finance.validation.month_locked'),
                ]);
            }
        }
    }
}
