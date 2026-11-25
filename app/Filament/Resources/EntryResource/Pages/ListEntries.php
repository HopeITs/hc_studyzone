<?php

namespace App\Filament\Resources\EntryResource\Pages;

use App\Enums\EntryType;
use App\Enums\Roles;
use App\Filament\Resources\EntryResource;
use App\Filament\Resources\EntryResource\Widgets\EntryStats;
use App\Models\AdmissionPrice;
use App\Models\Center;
use App\Models\LockedMonth;
use App\Support\FinanceDisplay;
use App\Support\WidgetPreferences;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ListEntries extends ListRecords
{
    use ExposesTableToWidgets {
        getWidgetData as private getTableWidgetData;
    }

    protected static string $resource = EntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('scheduleAdmissionPrice')
                ->label(__('general.admission_preview.schedule'))
                ->icon('heroicon-o-tag')
                ->visible(fn (): bool => auth()->user()?->role === Roles::GeneralAdmin()
                    && Schema::hasColumn('student_entries', 'daily_admission_fee'))
                ->schema([
                    Select::make('center_id')
                        ->label(__('general.admission_preview.center'))
                        ->options(fn (): array => Center::query()->get()->mapWithKeys(fn (Center $center): array => [$center->id => $center->name])->all())
                        ->searchable()
                        ->rules([Rule::exists('centers', 'id')])
                        ->required(),
                    Select::make('type')
                        ->label(__('general.admission_preview.type'))
                        ->options([
                            EntryType::Daily() => __('general.admission_preview.daily'),
                            EntryType::Monthly() => __('general.admission_preview.monthly'),
                        ])
                        ->in([EntryType::Daily(), EntryType::Monthly()])
                        ->required(),
                    TextInput::make('amount')
                        ->label(__('general.admission_preview.amount'))
                        ->suffix(fn (): string => __('general.admission_preview.'.(FinanceDisplay::enabled() ? 'old' : 'new')))
                        ->integer()
                        ->minValue(1)
                        ->maxValue(100000000)
                        ->rules(fn (): array => FinanceDisplay::enabled() ? ['multiple_of:100'] : [])
                        ->required(),
                    DatePicker::make('start')
                        ->label(__('general.admission_preview.start'))
                        ->minDate(today())
                        ->required(),
                    DatePicker::make('end')
                        ->label(__('general.admission_preview.end'))
                        ->afterOrEqual('start'),
                ])
                ->action(function (array $data): void {
                    abort_unless(auth()->user()?->role === Roles::GeneralAdmin(), 403);

                    if (LockedMonth::isLocked($data['start'])) {
                        throw ValidationException::withMessages(['mountedActions.0.data.start' => __('general.messages.month_locked_body')]);
                    }

                    $amount = (int) $data['amount'];
                    AdmissionPrice::schedule(
                        (int) $data['center_id'],
                        EntryType::from((int) $data['type']),
                        FinanceDisplay::enabled() ? intdiv($amount, 100) : $amount,
                        $data['start'],
                        $data['end'] ?? null,
                    );
                })
                ->successNotificationTitle(__('general.admission_preview.price_saved')),
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
