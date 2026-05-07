<?php

namespace App\Filament\Components;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
// use Filament\Support\Components\Concerns\HasExtraAttributes;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Session;
use Livewire\Component;

class LanguageSwitcher extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;
    // use HasExtraAttributes;

    public function switchLocale(string $locale): void
    {
        if (in_array($locale, config('app.available_locales', ['en']))) {
            Session::put('locale', $locale);
            app()->setLocale($locale);
        }

        $this->redirect(request()->header('Referer', url('/')));
    }

    public function render(): View
    {
        $currentLocale = app()->getLocale();

        return view('filament.components.language-switcher', compact('currentLocale'));
    }

    public function getEnglishAction(): Action
    {
        return Action::make('english')
            ->label('EN')
            ->color(app()->getLocale() === 'en' ? 'primary' : 'gray')
            ->action(fn () => $this->switchLocale('en'))
            ->size('sm')
            ->icon('heroicon-o-language');
    }

    public function getArabicAction(): Action
    {
        return Action::make('arabic')
            ->label('AR')
            ->color(app()->getLocale() === 'ar' ? 'primary' : 'gray')
            ->action(fn () => $this->switchLocale('ar'))
            ->size('sm')
            ->icon('heroicon-o-language');
    }
}
