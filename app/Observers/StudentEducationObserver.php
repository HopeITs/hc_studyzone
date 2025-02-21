<?php

namespace App\Observers;

use App\Models\StudentEducation;
use Illuminate\Support\Str;

class StudentEducationObserver
{
    /**
     * Handle the StudentEducation "created" event.
     */
    public function created(StudentEducation $studentEducation): void
    {
        //
    }

//    public function creating(StudentEducation $studentEducation): void
//    {
//        $studentEducation->id = Str::uuid();
//    }

    /**
     * Handle the StudentEducation "updated" event.
     */
    public function updated(StudentEducation $studentEducation): void
    {
        //
    }

    /**
     * Handle the StudentEducation "deleted" event.
     */
    public function deleted(StudentEducation $studentEducation): void
    {
        //
    }

    /**
     * Handle the StudentEducation "restored" event.
     */
    public function restored(StudentEducation $studentEducation): void
    {
        //
    }

    /**
     * Handle the StudentEducation "force deleted" event.
     */
    public function forceDeleted(StudentEducation $studentEducation): void
    {
        //
    }
}
