<?php

namespace App\Filament\Resources\EntryResource\Pages;

use App\Enums\Roles;
use App\Filament\Resources\EntryResource;
use App\Models\Center;
use App\Models\EntryService;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

class EditEntry extends EditRecord
{
    protected static string $resource = EntryResource::class;

    // Public properties to hold the totals for the Placeholder
    public $grandTotal = 0;
    public $itemsCount = 0;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->updateGrandTotal();
    }

    // Listen for the event dispatched by the RelationManager
    #[On('entry-services-updated')]
    public function updateGrandTotal()
    {
        $record = $this->getRecord();
        if ($record) {
            $record->refresh(); // Refresh to get the latest relations from DB
            $this->grandTotal = $record->services->sum('total');
            $this->itemsCount = $record->services->count();
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // ═══════════════════════════════════════════════════════
                // Entry Details Fieldset
                // ═══════════════════════════════════════════════════════
                Fieldset::make('تفاصيل الدخول')
                    ->schema([
                        Select::make('center_id')
                            ->label(__('general.labels.center'))
                            ->options(function () {
                                return Center::where('is_active', 1)
                                    ->when(
                                        auth()->user()->city_id,
                                        fn ($query, $cityId) => $query->where('city_id', $cityId)
                                    )
                                    ->pluck('name', 'id');
                            })
                            ->default(fn () => auth()->user()->center_id)
                            ->visible(fn () => auth()->user()->role !== Roles::CenterAdmin())
                            ->required()
                            ->searchable()
                            ->live()
                            ->columnSpan(1),

                        Hidden::make('_center_id_for_calc')
                            ->default(fn () => auth()->user()->center_id)
                            ->dehydrateStateUsing(fn ($state, callable $get) => $get('center_id') ?? auth()->user()->center_id),

                        Select::make('entry_type')
                            ->label(__('general.labels.entry_type'))
                            ->default(1)
                            ->options(__('general.entry-types'))
                            ->required()
                            ->columnSpan(1),

                        DateTimePicker::make('entry_time')
                            ->label(__('general.labels.entry_time'))
                            ->default(now())
                            ->maxDate(now()->endOfDay())
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                if (! $state || \Carbon\Carbon::parse($state)->isToday()) {
                                    return;
                                }

                                Notification::make()
                                    ->title(__('general.messages.non_today_entry_date_title'))
                                    ->body(__('general.messages.non_today_entry_date_body'))
                                    ->warning()
                                    ->send();
                            })
                            ->required()
                            ->columnSpan(1),
                    ])
                    ->columns(3),



                        Placeholder::make('grand_total_placeholder')
                            ->label('')
                            ->content(fn () => $this->itemsCount > 0 ? view('filament.forms.components.grand-total', [
                                'total' => $this->grandTotal,
                                'count' => $this->itemsCount,
                            ]) : null)
                            ->visible(fn () => $this->itemsCount > 0)
                            ->live()->columnSpanFull(),

            ]);
    }

    // التعامل مع عملية الحفظ في قاعدة البيانات
    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        $centerId = $data['center_id'] ?? auth()->user()->center_id;

        $record->update([
            'center_id' => $centerId,
            'entry_type' => $data['entry_type'],
            'entry_time' => $data['entry_time'],
        ]);

        // ⚠️ IMPORTANT: We removed the services saving logic from here because
        // the form schema no longer contains the 'services' repeater and 'day_services' checkbox list.
        // Keeping the old logic would erase your RelationManager additions on every save!
        // Services are now completely handled by the ServicesRelationManager.

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
