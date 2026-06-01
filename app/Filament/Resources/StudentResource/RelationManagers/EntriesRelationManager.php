<?php

namespace App\Filament\Resources\StudentResource\RelationManagers;

use App\Enums\Roles;
use App\Filament\Resources\StudentResource;
use App\Filament\Resources\EntryResource;
use App\Models\Center;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class EntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'entries';

    protected static ?string $title = 'الدخوليات';

    public function form(Form $form): Form
    {
        $user = Auth::user();

        return $form->schema([
            Forms\Components\Hidden::make('student_id')->default(fn ($livewire) => $livewire->ownerRecord->id),
            Forms\Components\Hidden::make('center_id')->default(fn () => $user->center_id)->visible($user->role === Roles::CenterAdmin())->required(),
            Forms\Components\Hidden::make('user_id')->default(fn () => $user->id)->required(),
            Select::make('center_id')
                ->label(__('general.labels.center'))
                ->options(Center::where('is_active', 1)->orderBy('order')->get()->pluck('name', 'id'))
                ->default(fn () => auth()->user()->center_id)
                ->visible(auth()->user()->role !== Roles::CenterAdmin())
                ->required()
                ->searchable(),
            Forms\Components\Select::make('entry_type')
                ->label(__('general.labels.entry_type'))
                ->options(__('general.entry-types'))
                ->default(1)
                ->required(),

            Forms\Components\DateTimePicker::make('entry_time')
                ->label(__('general.labels.entry_time'))
                ->default(now())
                ->required(),

            Forms\Components\Fieldset::make('الخدمات المطلوبة')
                ->label('الخدمات المطلوبة')
                ->schema([
                    Forms\Components\Select::make('selected_services')
                        ->label('اختر الخدمات')
                        ->multiple()
                        ->options(function () {
                            $services = \App\Helpers\ServiceHelper::getServicesWithPrices();
                            $options = [];
                            foreach ($services as $service) {
                                $options[$service['id']] = "{$service['name']} - {$service['price']} SYP";
                            }

                            return $options;
                        })
                        ->default(function () {
                            $defaultService = \App\Helpers\ServiceHelper::getDefaultEntryService();

                            return $defaultService ? [$defaultService['id']] : [];
                        })
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            $services = \App\Helpers\ServiceHelper::getServicesWithPrices();
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

                    \Filament\Forms\Components\ViewField::make('services_list')
                        ->label('الخدمات المختارة')
                        ->view('filament.forms.components.services-list')
                        ->afterStateHydrated(function ($component, $get) {
                            $component->state($get('selected_services_details'));
                        })
                        ->live()
                        ->columnSpanFull(),

                    \Filament\Forms\Components\Checkbox::make('calculate_total')
                        ->label('إغلاق الفاتورة وعرض الإجمالي')
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

                    \Filament\Forms\Components\ViewField::make('total_display')
                        ->label('الإجمالي')
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
            ->recordTitleAttribute('entry_time')
            ->defaultSort('entry_time','desc')
            ->columns([
                TextColumn::make('entry_type')
                    ->label('النوع')
                    ->formatStateUsing(fn ($state) => __('general.entry-types.'.$state))
                    ->sortable(),
                TextColumn::make('center.name')->label('المركز')->sortable(),
                TextColumn::make('entry_time')->label('التاريخ')->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('services_list')
                    ->label('الخدمات المقدمة')
                    ->formatStateUsing(fn ($record) => $record->services_list ?: '-')
                    ->toggleable(),
                TextColumn::make('total_amount')
                    ->label('الإجمالي')
                    ->formatStateUsing(fn ($record) => number_format($record->total_amount, 2).' SYP')
                    ->sortable()
                    ->color('success'),
            ])
      // Ensure this is imported at the top
            ->headerActions([
                Action::make('create_entry_page')
                    ->label('إضافة دخولية جديدة')
                    ->icon('heroicon-o-plus-circle')
                    // ✅ Use Filament's getUrl() instead of route()
                    ->url(fn () => StudentResource::getUrl('create-entry', [
                        'record' => $this->ownerRecord->id,
                    ]))
                    ->disabled(fn () => ! $this->ownerRecord->completed_data)
                    ->tooltip(fn () => ! $this->ownerRecord->completed_data
                        ? '❌ لا يمكن إضافة دخولية: بيانات الطالب غير مكتملة'
                        : 'إضافة دخولية جديدة')
                    ->action(function (array $data, $livewire) {
                        if (!$livewire->ownerRecord->completed_data) {
                            Notification::make()
                                ->title('❌ لا يمكن إدخال بيانات الطالب')
                                ->danger()
                                ->body('بيانات الطالب غير مكتملة. الرجاء مراجعة البيانات المطلوبة أولاً.')
                                ->send();
                            return;
                        }

                        $entry = $livewire->ownerRecord->entries()->create([
                            'student_id' => $livewire->ownerRecord->id,
                            'user_id' => Auth::id(),
                            'center_id' => $data['center_id'] ?? auth()->user()->center_id,
                            'entry_type' => $data['entry_type'],
                            'entry_time' => $data['entry_time'],
                        ]);

                        $defaultService = \App\Helpers\ServiceHelper::getDefaultEntryService();
                        $servicesToAdd = !empty($data['selected_services']) ? $data['selected_services'] : [];

                        if ($defaultService && !in_array($defaultService['id'], $servicesToAdd)) {
                            $servicesToAdd[] = $defaultService['id'];
                        }

                        if (!empty($servicesToAdd)) {
                            $services = \App\Helpers\ServiceHelper::getServicesWithPrices();
                            foreach ($services as $service) {
                                if (in_array($service['id'], $servicesToAdd)) {
                                    \App\Models\EntryService::create([
                                        'entry_id' => $entry->id,
                                        'service_id' => $service['id'],
                                        'price_at_time' => $service['price'],
                                    ]);
                                }
                            }
                        }
                    }),
            ])
            ->actions([
                Action::make('view_bill')
                    ->label('عرض الفاتورة')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->url(fn ($record) => EntryResource::getUrl('view', [
                        'record' => $record->id,

                    ]))
                    ->openUrlInNewTab(),

                // NEW: Edit Action pointing to the custom page
                Action::make('edit_entry')
                    ->label('تعديل')
                    ->icon('heroicon-o-pencil')
                    ->color('warning')
                    ->url(fn ($record) => EntryResource::getUrl('edit', [
                        'record' => $record->id, // Entry ID

                    ]))
                    ->disabled(fn ($record) => ! $record->student->completed_data),

                Actions\DeleteAction::make(),
            ])

            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
