<?php

namespace App\Observers;

use App\Models\StudentEntry;
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
     * Handle the StudentEntry "updated" event.
     */
    public function updated(StudentEntry $studentEntry): void
    {
        //
    }

    /**
     * Handle the StudentEntry "deleted" event.
     */
    public function deleted(StudentEntry $studentEntry): void
    {
        //
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
