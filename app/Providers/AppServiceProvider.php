<?php

namespace App\Providers;

use App\Models\Student;
use App\Models\StudentEducation;
use App\Models\StudentEntry;
use App\Observers\StudentEducationObserver;
use App\Observers\StudentEntryObserver;
use App\Observers\StudentObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Student::observe(StudentObserver::class);
        StudentEducation::observe(StudentEducationObserver::class);
        StudentEntry::observe(StudentEntryObserver::class);
    }
}
