<?php

namespace App\Filament\Resources\EntryResource\Pages;

use App\Filament\Resources\EntryResource;
use App\Filament\Resources\EntryResource\Widgets\EntryStats;
use App\Support\WidgetPreferences;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListEntries extends ListRecords
{
    use ExposesTableToWidgets {
        getWidgetData as private getTableWidgetData;
    }

    protected static string $resource = EntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('chooseSummaryCards')
                ->label(__('general.labels.choose_summary_cards'))
                ->icon('heroicon-o-adjustments-horizontal')
                ->fillForm(fn (): array => [
                    'cards' => array_values(array_diff(
                        array_keys(EntryStats::cardOptions()),
                        WidgetPreferences::get('entries'),
                    )),
                ])
                ->schema([
                    CheckboxList::make('cards')
                        ->label(__('general.labels.visible_summary_cards'))
                        ->options(fn (): array => EntryStats::cardOptions())
                        ->columns(['default' => 1, 'sm' => 2])
                        ->bulkToggleable(),
                ])
                ->modalSubmitActionLabel(__('general.labels.save_widget_layout'))
                ->action(function (array $data): void {
                    abort_unless(EntryResource::canViewAny(), 403);

                    WidgetPreferences::save('entries', array_diff(
                        array_keys(EntryStats::cardOptions()),
                        $data['cards'] ?? [],
                    ));
                }),
        ];
    }

    public function getWidgetData(): array
    {
        return [
            ...$this->getTableWidgetData(),
            'hiddenCards' => WidgetPreferences::get('entries'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            EntryStats::class,
        ];
    }
}
