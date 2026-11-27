<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EntryType;
use App\Support\MonthlySubscription;
use App\Traits\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Price history of the admission categories (daily entry, monthly
 * subscription). Rows of one category and one scope never overlap:
 * center_id NULL is the default price for every center, a center_id row
 * overrides the default in that center for its own period.
 */
class AdmissionPrice extends Model
{
    use ActivityLog;

    /** Admission categories that carry a price. */
    public const TYPES = [EntryType::Daily, EntryType::Monthly];

    protected $fillable = ['center_id', 'entry_type', 'amount', 'starts_on', 'ends_on'];

    protected $casts = [
        'starts_on' => 'date:Y-m-d',
        'ends_on' => 'date:Y-m-d',
        'amount' => 'integer',
        'entry_type' => 'integer',
    ];

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function isDefault(): bool
    {
        return $this->center_id === null;
    }

    /** scheduled | active | expired, relative to today. */
    public function status(): string
    {
        if ($this->starts_on->isAfter(today())) {
            return 'scheduled';
        }

        return $this->ends_on !== null && $this->ends_on->isBefore(today()) ? 'expired' : 'active';
    }

    public function scopeCovering(Builder $query, string $date): Builder
    {
        return $query->whereDate('starts_on', '<=', $date)
            ->where(fn (Builder $query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $date));
    }

    public function scopeOfScope(Builder $query, ?int $centerId): Builder
    {
        return $centerId === null ? $query->whereNull('center_id') : $query->where('center_id', $centerId);
    }

    /** The price a center pays for a category on a day: its own price, else the default. */
    public static function amountOn(int $centerId, EntryType $type, string $date): ?int
    {
        $amount = static::query()
            ->where('entry_type', $type->value)
            ->where(fn (Builder $query) => $query->where('center_id', $centerId)->orWhereNull('center_id'))
            ->covering($date)
            ->orderByRaw('center_id IS NULL')
            ->orderByDesc('starts_on')
            ->value('amount');

        return $amount === null ? null : (int) $amount;
    }

    public static function dailyFeeOn(int $centerId, string $date): ?int
    {
        return static::amountOn($centerId, EntryType::Daily, $date);
    }

    /**
     * Add a price to the history of a category, for every center
     * ($centerId null) or one center. The start may be in the past: the
     * price that was running then is closed the day before, and the daily
     * fee of the entries already recorded in the new period is recalculated.
     *
     * @throws ValidationException
     */
    public static function schedule(?int $centerId, EntryType $type, int $amount, string $start, ?string $end = null): self
    {
        abort_unless(in_array($type, self::TYPES, true), 422);

        $start = Carbon::parse($start)->toDateString();
        $end = $end ? Carbon::parse($end)->toDateString() : null;

        if ($end !== null && $end < $start) {
            static::fail('ends_on', 'end_before_start');
        }

        return DB::transaction(function () use ($centerId, $type, $amount, $start, $end): self {
            if ($centerId !== null) {
                Center::query()->lockForUpdate()->findOrFail($centerId);
            }

            /** @var Collection<int, self> $rows */
            $rows = static::query()
                ->where('entry_type', $type->value)
                ->ofScope($centerId)
                ->orderBy('starts_on')
                ->lockForUpdate()
                ->get();

            if ($rows->contains(fn (self $row) => $row->starts_on->toDateString() === $start)) {
                static::fail('starts_on', 'same_start');
            }

            $previous = $rows->last(fn (self $row) => $row->starts_on->toDateString() < $start);
            $next = $rows->first(fn (self $row) => $row->starts_on->toDateString() > $start);
            $nextStart = $next?->starts_on->toDateString();

            if ($next !== null) {
                $lastFree = Carbon::parse($nextStart)->subDay()->toDateString();

                if ($end !== null && $end > $lastFree) {
                    static::fail('ends_on', 'overlap_next', ['date' => $nextStart]);
                }

                $end ??= $lastFree;
            }

            if ($previous !== null && ($previous->ends_on === null || $previous->ends_on->toDateString() >= $start)) {
                // Only a period that simply ran until the next change may be
                // shortened; a price given its own end date is left intact.
                $runsUntilNext = $previous->ends_on === null
                    || ($nextStart !== null && $previous->ends_on->copy()->addDay()->toDateString() === $nextStart);

                if (! $runsUntilNext) {
                    static::fail('starts_on', 'overlap_previous', ['date' => $previous->ends_on->toDateString()]);
                }
            }

            static::assertMonthsOpen($start, $end);

            if ($previous !== null && ($previous->ends_on === null || $previous->ends_on->toDateString() >= $start)) {
                $previous->update(['ends_on' => Carbon::parse($start)->subDay()->toDateString()]);
            }

            $price = static::query()->create([
                'center_id' => $centerId,
                'entry_type' => $type->value,
                'amount' => $amount,
                'starts_on' => $start,
                'ends_on' => $end,
            ]);

            if ($type === EntryType::Daily) {
                static::recalculateDailyFees($centerId, $start, $end);
            } else {
                MonthlySubscription::recalculateFees($centerId, $start, $end);
            }

            return $price;
        }, 3);
    }

    /**
     * Remove a price that has not started yet; the period before it runs
     * on again as it did before the price was scheduled.
     *
     * @throws ValidationException
     */
    public function cancel(): void
    {
        DB::transaction(function (): void {
            $row = static::query()->lockForUpdate()->findOrFail($this->getKey());

            if ($row->status() !== 'scheduled') {
                static::fail('starts_on', 'only_scheduled');
            }

            $previous = static::query()
                ->where('entry_type', $row->entry_type)
                ->ofScope($row->center_id)
                ->whereDate('starts_on', '<', $row->starts_on->toDateString())
                ->whereDate('ends_on', $row->starts_on->copy()->subDay()->toDateString())
                ->lockForUpdate()
                ->first();

            $row->delete();

            if ($previous !== null) {
                $next = static::query()
                    ->where('entry_type', $row->entry_type)
                    ->ofScope($row->center_id)
                    ->whereDate('starts_on', '>', $row->starts_on->toDateString())
                    ->orderBy('starts_on')
                    ->value('starts_on');

                $previous->update(['ends_on' => $next ? Carbon::parse($next)->subDay()->toDateString() : null]);
            }
        });
    }

    /**
     * Re-read the daily fee of the daily entries recorded between two days,
     * in one center or (for a default price) in every center.
     */
    public static function recalculateDailyFees(?int $centerId, string $from, ?string $to): void
    {
        $to = min($to ?? today()->toDateString(), today()->toDateString());

        if ($to < $from) {
            return;
        }

        $groups = DB::table('student_entries')
            ->where('entry_type', EntryType::Daily())
            ->when($centerId !== null, fn ($query) => $query->where('center_id', $centerId))
            ->whereDate('entry_time', '>=', $from)
            ->whereDate('entry_time', '<=', $to)
            ->selectRaw('center_id, DATE(entry_time) as day')
            ->distinct()
            ->get();

        foreach ($groups as $group) {
            DB::table('student_entries')
                ->where('entry_type', EntryType::Daily())
                ->where('center_id', $group->center_id)
                ->whereDate('entry_time', $group->day)
                ->update(['daily_admission_fee' => static::dailyFeeOn((int) $group->center_id, (string) $group->day)]);
        }
    }

    /** A locked month keeps its entries, so no price may reach into it. */
    protected static function assertMonthsOpen(string $start, ?string $end): void
    {
        $last = Carbon::parse(min($end ?? today()->toDateString(), today()->toDateString()))->startOfMonth();

        for ($month = Carbon::parse($start)->startOfMonth(); $month->lte($last); $month->addMonth()) {
            if (LockedMonth::isLocked($month)) {
                static::fail('starts_on', 'month_locked', ['month' => $month->translatedFormat('F Y')]);
            }
        }
    }

    /** @throws ValidationException */
    protected static function fail(string $field, string $key, array $replace = []): never
    {
        throw ValidationException::withMessages([
            $field => __('general.admission_categories.errors.'.$key, $replace),
        ]);
    }
}
