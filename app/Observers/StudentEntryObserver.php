<?php

namespace App\Observers;

use App\Enums\EntryType;
use App\Models\AdmissionPrice;
use App\Models\LockedMonth;
use App\Models\StudentEntry;
use App\Support\MonthlySubscription;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class StudentEntryObserver
{
    /**
     * Handle the StudentEntry "created" event.
     */
    public function created(StudentEntry $studentEntry): void
    {
        //
    }
    public function creating(StudentEntry $entry): void
    {
        $entry->id = Str::uuid();
    }

    /**
     * Block any create/update once either the entry's original month or its
     * (possibly new) target month has been locked by the General Admin —
     * covers both "editing a locked entry" and "moving an entry into a
     * locked month".
     */
    public function saving(StudentEntry $entry): void
    {
        if ($entry->entry_time && Carbon::parse($entry->entry_time)->isAfter(today()->endOfDay())) {
            Notification::make()
                ->title(__('general.messages.future_entry_date_title'))
                ->body(__('general.messages.future_entry_date_body'))
                ->danger()
                ->send();

            throw ValidationException::withMessages([
                'entry_time' => __('general.messages.future_entry_date_body'),
            ]);
        }

        $datesToCheck = array_filter([
            $entry->entry_time,
            $entry->exists ? $entry->getOriginal('entry_time') : null,
        ]);

        foreach ($datesToCheck as $date) {
            if (LockedMonth::isLocked($date)) {
                $this->haltForLockedMonth($date);
            }
        }

        $visitDate = $entry->entry_time?->toDateString();
        $pricingChanged = ! $entry->exists || $entry->isDirty(['center_id', 'entry_type'])
            || $visitDate !== $entry->getOriginal('entry_time')?->toDateString();

        if ($visitDate && $pricingChanged && Schema::hasColumn('student_entries', 'daily_admission_fee')) {
            $entry->daily_admission_fee = (int) $entry->entry_type === EntryType::Daily()
                ? AdmissionPrice::dailyFeeOn((int) $entry->center_id, $visitDate)
                : null;
        }

        // A monthly visit recorded or changed from now on is tracked; whether
        // it pays or is covered is settled in saved(), once it is in the month.
        if (($pricingChanged || $entry->isDirty('student_id')) && Schema::hasColumn('student_entries', 'monthly_status')) {
            $isMonthly = (int) $entry->entry_type === EntryType::Monthly();
            $entry->monthly_status = $isMonthly ? MonthlySubscription::CHARGED : null;
            $entry->monthly_admission_fee = null;
        }
    }

    public function saved(StudentEntry $entry): void
    {
        if (! Schema::hasColumn('student_entries', 'monthly_status')
            || ! ($entry->wasRecentlyCreated || $entry->wasChanged(['student_id', 'center_id', 'entry_type', 'entry_time']))) {
            return;
        }

        $this->refreshMonthly($entry->student_id, $entry->center_id, $entry->entry_type, $entry->entry_time);

        if (! $entry->wasRecentlyCreated) {
            $original = $entry->getOriginal();
            $this->refreshMonthly($original['student_id'] ?? null, $original['center_id'] ?? null, $original['entry_type'] ?? null, $original['entry_time'] ?? null);
        }
    }

    protected function refreshMonthly(mixed $studentId, mixed $centerId, mixed $entryType, mixed $time): void
    {
        if ($studentId && $centerId && $time && (int) $entryType === EntryType::Monthly()) {
            MonthlySubscription::refresh((string) $studentId, (int) $centerId);
        }
    }

    /**
     * Handle the StudentEntry "updated" event.
     */
    public function updated(StudentEntry $studentEntry): void
    {
        //
    }

    public function deleting(StudentEntry $entry): void
    {
        if (LockedMonth::isLocked($entry->entry_time)) {
            $this->haltForLockedMonth($entry->entry_time);
        }
    }

    /**
     * Handle the StudentEntry "deleted" event.
     */
    public function deleted(StudentEntry $studentEntry): void
    {
        // The next visit of the month takes over paying the subscription.
        if (Schema::hasColumn('student_entries', 'monthly_status')) {
            $this->refreshMonthly($studentEntry->student_id, $studentEntry->center_id, $studentEntry->entry_type, $studentEntry->entry_time);
        }
    }

    protected function haltForLockedMonth(mixed $date): never
    {
        Notification::make()
            ->title(__('general.messages.month_locked_title'))
            ->body(__('general.messages.month_locked_body'))
            ->danger()
            ->send();

        throw new Halt;
    }

    /**
     * Handle the StudentEntry "restored" event.
     */
    public function restored(StudentEntry $studentEntry): void
    {
        //
    }

    /**
     * Handle the StudentEntry "force deleted" event.
     */
    public function forceDeleted(StudentEntry $studentEntry): void
    {
        //
    }
}
