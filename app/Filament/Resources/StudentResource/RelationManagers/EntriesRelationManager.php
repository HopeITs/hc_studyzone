<?php

namespace App\Filament\Resources\StudentResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\DateTimePicker;
use Carbon\Carbon;
use Filament\Schemas\Components\Fieldset;
use App\Helpers\ServiceHelper;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Components\Checkbox;
use App\Enums\EntryPurpose;
use Filament\Actions\Action;
use App\Models\EntryService;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Enums\Roles;
use App\Filament\Resources\StudentResource;
use App\Filament\Resources\EntryResource;
use App\Models\Center;
use App\Models\LockedMonth;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'entries';

    protected static ?string $title = null;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return EntryResource::canViewAny() && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('general.labels.entries');
    }

    public function form(Schema $schema): Schema
    {
        $user = Auth::user();

        return $schema->components([
            Hidden::make('student_id')->default(fn ($livewire) => $livewire->ownerRecord->id),
            Hidden::make('center_id')->default(fn () => $user->center_id)->visible($user->role === Roles::CenterAdmin())->required(),
            Hidden::make('user_id')->default(fn () => $user->id)->required(),
            Select::make('center_id')
                ->label(__('general.labels.center'))
                ->options(Center::where('is_active', 1)->orderBy('order')->get()->pluck('name', 'id'))
                ->default(fn () => auth()->user()->center_id)
                ->visible(auth()->user()->role !== Roles::CenterAdmin())
                ->required()
                ->searchable(),
            Select::make('entry_type')
                ->label(__('general.labels.entry_type'))
                ->options(__('general.entry-types'))
                ->default(1)
                ->required(),

            DateTimePicker::make('entry_time')
                ->label(__('general.labels.entry_time'))
                ->default(now())
                ->maxDate(now()->endOfDay())
                ->live()
                ->afterStateUpdated(function ($state) {
                    if (! $state || Carbon::parse($state)->isToday()) {
                        return;
                    }

                    Notification::make()
                        ->title(__('general.messages.non_today_entry_date_title'))
                        ->body(__('general.messages.non_today_entry_date_body'))
                        ->warning()
                        ->send();
                })
                ->required(),

            Fieldset::make('requested_services')
                ->label(__('general.labels.requested_services'))
                ->schema([
                    Select::make('selected_services')
                        ->label(__('general.labels.select_services'))
                        ->multiple()
                        ->options(function () {
                            $services = ServiceHelper::getServicesWithPrices();
                            $options = [];
                            foreach ($services as $service) {
                                $options[$service['id']] = "{$service['name']} - {$service['price']} SYP";
                            }

                            return $options;
                        })
                        ->default(function () {
                            $defaultService = ServiceHelper::getDefaultEntryService();

                            return $defaultService ? [$defaultService['id']] : [];
                        })
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            $services = ServiceHelper::getServicesWithPrices();
                            $selectedDetails = [];
                            $total = 0;

                            foreach ($services as $service) {
                                if (in_array($service['id'], $state)) {
                                    $selectedDetails[] = [
                                        'id' => $service['id'],
                                        'name' => $service['name'],
                                        'price' => $service['price'],
                                    ];
                                    $total += $service['price'];
                                }
                            }

                            $set('selected_services_details', $selectedDetails);
                            $set('total_amount', $total);
                        })
                        ->columnSpanFull(),

                    ViewField::make('services_list')
                        ->label(__('general.labels.selected_services'))
                        ->view('filament.forms.components.services-list')
                        ->afterStateHydrated(function ($component, $get) {
                            $component->state($get('selected_services_details'));
                        })
                        ->live()
                        ->columnSpanFull(),

                    Checkbox::make('calculate_total')
                        ->label(__('general.labels.close_invoice_show_total'))
                        ->live()
                        ->afterStateUpdated(function ($state, callable $get, callable $set) {
                            if ($state) {
                                $total = $get('total_amount');
                                $set('total_display', $total);
                            } else {
                                $set('total_display', null);
                            }
                        })
                        ->columnSpanFull(),

                    ViewField::make('total_display')
                        ->label(__('general.labels.total'))
                        ->view('filament.forms.components.total-display')
                        ->afterStateHydrated(function ($component, $get) {
                            $component->state($get('total_display'));
                        })
                        ->live()
                        ->columnSpanFull(),
                ])
                ->columns(1),
        ])->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->whereIn('student_entries.id', EntryResource::getEloquentQuery()->select('student_entries.id')))
            ->recordTitleAttribute('entry_time')
            ->defaultSort('entry_time','desc')
            ->columns([
                TextColumn::make('entry_type')
                    ->label(__('general.labels.type'))
                    ->formatStateUsing(fn ($state) => __('general.entry-types.'.$state))
                    ->sortable(),
                TextColumn::make('purpose')
                    ->label(__('general.labels.purpose'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => __('general.purposes.'.$state))
                    ->color(fn (int $state): string => match ($state) {
                        EntryPurpose::Unknown() => 'warning',
                        EntryPurpose::Studying() => 'info',
                        EntryPurpose::Working() => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('center.name')->label(__('general.labels.center'))->sortable(),
                TextColumn::make('entry_time')->label(__('general.labels.date'))->dateTime('Y-m-d H:i')->sortable(),
                CheckboxColumn::make('use_internet')
                    ->label(__('general.labels.internet-service'))
                    ->disabled(fn ($record) => ! EntryResource::canEdit($record) || ! ServiceHelper::centerHasInternetService($record->center_id))
                    ->extraAttributes(fn ($record) => ServiceHelper::centerHasInternetService($record->center_id) ? [] : ['class' => 'hidden'])
                    ->updateStateUsing(function ($record, $state) {
                        abort_unless(EntryResource::canEdit($record), 403);

                        return ServiceHelper::toggleInternetService($record, (bool) $state);
                    }),
                TextColumn::make('services_list')
                    ->label(__('general.labels.provided_services'))
                    ->formatStateUsing(fn ($record) => $record->services_list ?: '-')
                    ->toggleable(),
                TextColumn::make('total_amount')
                    ->label(__('general.labels.total'))
                    ->formatStateUsing(fn ($record) => number_format($record->total_amount, 2).' SYP')
                    ->sortable()
                    ->color('success'),
            ])
      // Ensure this is imported at the top
            ->headerActions([
                Action::make('create_entry_page')
                    ->label(__('general.labels.add_entry'))
                    ->icon('heroicon-o-plus-circle')
                    // ✅ Use Filament's getUrl() instead of route()
                    ->url(fn () => StudentResource::getUrl('create-entry', [
                        'record' => $this->ownerRecord->id,
                    ]))
                    ->visible(fn (): bool => EntryResource::canCreateForStudent($this->ownerRecord))
                    ->disabled(fn () => ! $this->ownerRecord->completed_data)
                    ->tooltip(fn () => ! $this->ownerRecord->completed_data
                        ? '❌ '.__('general.labels.create_entry_disabled_tooltip')
                        : __('general.labels.add_entry')),
            ])
            ->recordActions([
                Action::make('view_bill')
                    ->label(__('general.labels.view_invoice'))
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->url(fn ($record) => EntryResource::getUrl('view', [
                        'record' => $record->id,

                    ]))
                    ->openUrlInNewTab(),

                // NEW: Edit Action pointing to the custom page
                Action::make('edit_entry')
                    ->label(__('general.labels.edit'))
                    ->icon('heroicon-o-pencil')
                    ->color('warning')
                    ->url(fn ($record) => EntryResource::getUrl('edit', [
                        'record' => $record->id, // Entry ID

                    ]))
                    ->disabled(fn ($record) => ! $record->student->completed_data || ! EntryResource::canEdit($record)),

                DeleteAction::make()
                    ->authorize(fn (Model $record): bool => EntryResource::canDelete($record)),
            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorize(fn (): bool => EntryResource::canDeleteAny())->authorizeIndividualRecords(fn (Model $record): bool => EntryResource::canDelete($record)),
                ]),
            ])
            ->checkIfRecordIsSelectableUsing(fn ($record) => ! LockedMonth::isLocked($record->entry_time));
    }
}
