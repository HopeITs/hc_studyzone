<?php

namespace App\Providers;

use App\Models\Student;
use App\Models\StudentEducation;
use App\Models\StudentEntry;
use App\Observers\StudentEducationObserver;
use App\Observers\StudentEntryObserver;
use App\Observers\StudentObserver;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

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

        Livewire::useScriptTagAttributes(['defer' => true]);

        // Filament registers its core/plugin <script> tags without defer by
        // default. Js assets are mutable objects shared by reference, so this
        // flips the flag after every provider (Filament's included) has
        // finished registering its assets.
        $this->app->booted(function () {
            foreach (FilamentAsset::getScripts() as $script) {
                $script->defer();
            }
        });
    }
}
