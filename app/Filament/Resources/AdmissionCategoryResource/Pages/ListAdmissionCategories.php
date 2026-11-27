<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdmissionCategoryResource\Pages;

use App\Filament\Resources\AdmissionCategoryResource;
use App\Support\FinanceDisplay;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAdmissionCategories extends ListRecords
{
    protected static string $resource = AdmissionCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('currency')
                ->label(fn (): string => AdmissionCategoryResource::currency())
                ->icon('heroicon-o-arrows-right-left')
                ->color('gray')
                ->action(fn () => FinanceDisplay::toggle()),
            CreateAction::make()
                ->label(__('general.admission_categories.add_price'))
                ->createAnother(false)
                ->fillForm(fn (): array => [
                    'entry_type' => is_numeric($this->activeTab) ? (int) $this->activeTab : array_key_first(AdmissionCategoryResource::typeOptions()),
                    'is_default' => true,
                    'starts_on' => today()->toDateString(),
                ])
                ->using(fn (array $data) => AdmissionCategoryResource::savePrice($data))
                ->successNotificationTitle(__('general.admission_categories.saved')),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [];

        foreach (AdmissionCategoryResource::typeOptions() as $type => $label) {
            $tabs[(string) $type] = Tab::make($label)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('entry_type', $type));
        }

        return $tabs;
    }
}
