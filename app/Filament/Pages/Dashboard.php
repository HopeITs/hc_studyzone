<?php

namespace App\Filament\Pages;

use App\Enums\Roles;
use App\Models\Center;
use App\Models\City;
use App\Support\WidgetPreferences;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use BaseDashboard\Concerns\HasFiltersForm;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reorderDashboardWidgets')
                ->label(__('general.labels.reorder_dashboard_widgets'))
                ->icon('heroicon-o-arrows-up-down')
                ->fillForm(fn (): array => [
                    'widgets' => array_map(
                        fn (string $id): array => ['id' => $id],
                        array_keys($this->getOrderedWidgets()),
                    ),
                ])
                ->schema([
                    Repeater::make('widgets')
                        ->hiddenLabel()
                        ->schema([Hidden::make('id')->required()])
                        ->itemLabel(fn (array $state): string => $this->widgetLabel($state['id'] ?? ''))
                        ->addable(false)
                        ->deletable(false)
                        ->reorderableWithButtons(),
                ])
                ->modalSubmitActionLabel(__('general.labels.save_widget_layout'))
                ->action(function (array $data): void {
                    abort_unless(static::canAccess(), 403);

                    $allowed = array_keys($this->getOrderedWidgets());
                    $submitted = array_column($data['widgets'] ?? [], 'id');

                    WidgetPreferences::save('dashboard', array_unique(array_intersect($submitted, $allowed)));
                }),
        ];
    }

    public function getWidgets(): array
    {
        return array_values($this->getOrderedWidgets());
    }

    private function getOrderedWidgets(): array
    {
        $widgets = [];
        foreach ($this->filterVisibleWidgets(parent::getWidgets()) as $widget) {
            $widgets[$this->normalizeWidgetClass($widget)] = $widget;
        }

        $saved = array_flip(WidgetPreferences::get('dashboard'));

        return array_replace(array_intersect_key($saved, $widgets), $widgets);
    }

    private function widgetLabel(string $id): string
    {
        $class = class_basename($id);

        $key = match ($class) {
            'ActiveCenterEntriesChart' => 'general.labels.entries_per_active_center',
            'ActiveDaysByCenter' => 'general.labels.active_days_by_center',
            'AllEntriesChart' => 'general.labels.entries_per_month',
            'EntriesByCenterTable' => 'general.labels.entries_by_center',
            'EntryGenderChart' => 'general.labels.entries_gender_distribution',
            'EntryToolsChart' => 'general.labels.entry_tool_usage_overview',
            'FrequentStudentsChart' => 'general.labels.frequent_students',
            'HourlyAverageChart' => 'general.labels.hourly_entries_percentage',
            'NewStudentsChart' => 'general.labels.new_students',
            'RevenueChart' => 'finance.widgets.daily_revenue',
            'SalesByCategoryChart' => 'finance.widgets.sales_by_category',
            'StatsOverviewWidget' => 'general.labels.statistics',
            'StudentGenderChart' => 'general.labels.new_students_gender_distribution',
            'TopEducationsStudentsEntriesTable' => 'general.labels.top_educations_by_students',
            'TopEducationsTable' => 'general.labels.top_educations_by_entries',
            'TopLevels' => 'general.labels.top_levels_by_students',
            'TopLevelsEntriesTable' => 'general.labels.top_levels_by_entries',
            default => null,
        };

        return $key === null ? str($class)->headline()->toString() : __($key);
    }

    public function getTitle(): string
    {
        return __('general.labels.statistics');
    }

    public function filtersForm(Schema $schema): Schema
    {
        $userRole = auth()->user()->role;

        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Select::make('city')->label(__('general.labels.city'))->options(City::pluck('name', 'id'))->multiple()
                            ->visible($userRole === Roles::GeneralAdmin() || $userRole === Roles::Observer())->columnSpanFull(),
                        Select::make('center')
                            ->label(__('general.labels.center'))
                            ->options(
                                $userRole === Roles::GeneralAdmin() || $userRole === Roles::Observer()
                                    ? Center::orderBy('is_active', 'desc')->orderBy('order')->pluck('name', 'id')
                                    : Center::where('city_id', auth()->user()->city_id)->orderBy('order')->orderBy('is_active', 'desc')->pluck('name', 'id')
                            )
                            ->multiple()
                            ->columnSpanFull(),
                        DatePicker::make('startDate')
                            ->label(__('general.labels.start_date_filter'))
                            ->default(now()->startOfYear())
                            ->maxDate(fn (Get $get) => $get('endDate') ?: now()),
                        DatePicker::make('endDate')
                            ->label(__('general.labels.end_date_filter'))
                            ->default(now())
                            ->maxDate(now()),
                    ])
                    ->columns(2),
            ]);
    }
}
