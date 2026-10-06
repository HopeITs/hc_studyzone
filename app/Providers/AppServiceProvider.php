<?php

namespace App\Providers;

use App\Models\EntryService;
use App\Models\Student;
use App\Models\StudentEducation;
use App\Models\StudentEntry;
use App\Observers\EntryServiceObserver;
use App\Observers\StudentEducationObserver;
use App\Observers\StudentEntryObserver;
use App\Observers\StudentObserver;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Facades\FilamentAsset;
use Filament\Tables\Table;
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
        EntryService::observe(EntryServiceObserver::class);

        $this->preserveFilamentV3Defaults();

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

    /**
     * Filament 4 changed several defaults. Keep the v3 layout/table behaviour the
     * existing forms and tables were designed around. Tables that opt in with
     * ->deferFilters() explicitly still get deferred filters.
     */
    private function preserveFilamentV3Defaults(): void
    {
        Fieldset::configureUsing(fn (Fieldset $fieldset) => $fieldset->columnSpanFull());
        Grid::configureUsing(fn (Grid $grid) => $grid->columnSpanFull());
        Section::configureUsing(fn (Section $section) => $section->columnSpanFull());

        Table::configureUsing(fn (Table $table) => $table
            ->deferFilters(false)
            ->defaultKeySort(false));
    }
}
