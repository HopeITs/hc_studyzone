<?php

namespace App\Observers;

use App\Models\LockedMonth;
use App\Models\StudentEntry;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Str;

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
        $datesToCheck = array_filter([
            $entry->entry_time,
            $entry->exists ? $entry->getOriginal('entry_time') : null,
        ]);

        foreach ($datesToCheck as $date) {
            if (LockedMonth::isLocked($date)) {
                $this->haltForLockedMonth($date);
            }
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
        //
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
