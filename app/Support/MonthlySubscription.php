<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\EntryType;
use App\Models\AdmissionPrice;
use App\Models\LockedMonth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A monthly subscription is paid on a student's monthly visit to a center
 * and covers their monthly visits there for one month from that day.
 */
class MonthlySubscription
{
    public const CHARGED = 'charged';

    public const COVERED = 'covered';

    /**
     * Re-derive charged/covered for all monthly visits of a student in one
     * center, e.g. after a visit was added, moved or deleted. A subscription
     * starts on the visit that pays it and runs one month (Nov 15 → Dec 14);
     * the next visit after that pays a new one. Visits recorded before
     * subscriptions were tracked (status NULL) are left as they are, but
     * still open a period, since their payment date is unknown.
     */
    public static function refresh(string $studentId, int $centerId): void
    {
        $rows = DB::table('student_entries')
            ->where('student_id', $studentId)
            ->where('center_id', $centerId)
            ->where('entry_type', EntryType::Monthly())
            ->orderBy('entry_time')
            ->orderBy('id')
            ->get(['id', 'entry_time', 'monthly_status', 'monthly_admission_fee']);

        $coveredUntil = null;

        foreach ($rows as $row) {
            $day = Carbon::parse($row->entry_time)->startOfDay();
            $pays = $coveredUntil === null || $day->gt($coveredUntil);

            if ($pays) {
                $coveredUntil = static::coveredUntil($day);
            }

            // Untracked visits, and visits inside a locked month, keep what they have.
            if ($row->monthly_status === null || LockedMonth::isLocked($day)) {
                continue;
            }

            $values = $pays
                ? [
                    'monthly_status' => self::CHARGED,
                    'monthly_admission_fee' => AdmissionPrice::amountOn($centerId, EntryType::Monthly, $day->toDateString()),
                ]
                : ['monthly_status' => self::COVERED, 'monthly_admission_fee' => null];

            if ($row->monthly_status !== $values['monthly_status']
                || ($row->monthly_admission_fee === null ? null : (int) $row->monthly_admission_fee) !== $values['monthly_admission_fee']) {
                DB::table('student_entries')->where('id', $row->id)->update($values);
            }
        }
    }

    /** Last day covered by a subscription paid on $day. */
    public static function coveredUntil(Carbon $day): Carbon
    {
        return $day->copy()->addMonthNoOverflow()->subDay();
    }

    /** Re-read the fee of the charged visits between two days after a price change. */
    public static function recalculateFees(?int $centerId, string $from, ?string $to): void
    {
        $to = min($to ?? today()->toDateString(), today()->toDateString());

        if ($to < $from) {
            return;
        }

        $groups = DB::table('student_entries')
            ->where('entry_type', EntryType::Monthly())
            ->where('monthly_status', self::CHARGED)
            ->when($centerId !== null, fn ($query) => $query->where('center_id', $centerId))
            ->whereDate('entry_time', '>=', $from)
            ->whereDate('entry_time', '<=', $to)
            ->selectRaw('center_id, DATE(entry_time) as day')
            ->distinct()
            ->get();

        foreach ($groups as $group) {
            DB::table('student_entries')
                ->where('entry_type', EntryType::Monthly())
                ->where('monthly_status', self::CHARGED)
                ->where('center_id', $group->center_id)
                ->whereDate('entry_time', $group->day)
                ->update(['monthly_admission_fee' => AdmissionPrice::amountOn((int) $group->center_id, EntryType::Monthly, (string) $group->day)]);
        }
    }

    /** "Monthly" / "Covered monthly subscription" label of an entry. */
    public static function typeLabel(int $entryType, ?string $status): string
    {
        return $entryType === EntryType::Monthly() && $status === self::COVERED
            ? __('general.admission_categories.monthly_covered')
            : __('general.entry-types.'.$entryType);
    }
}
