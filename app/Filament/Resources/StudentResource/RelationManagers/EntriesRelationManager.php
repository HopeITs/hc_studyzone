<?php

namespace App\Filament\Resources\StudentResource\RelationManagers;

use App\Enums\Roles;
use App\Models\Center;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Components\Hidden;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Actions;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class EntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'entries';
    protected static ?string $title = 'الدخوليات';

    public function form(Form $form): Form
    {
        $user = Auth::user();
        return $form->schema([
            Forms\Components\Hidden::make('student_id')->default(fn($livewire) => $livewire->ownerRecord->id),
            Forms\Components\Hidden::make('center_id')->default(fn() => $user->center_id)->visible($user->role === Roles::CenterAdmin())->required(),
            Forms\Components\Hidden::make('user_id')->default(fn() => $user->id)->required(),
            Select::make('center_id')
                ->label(__('general.labels.center'))
                ->options(Center::where('is_active', 1)->orderBy('order')->get()->pluck('name', 'id'))
                ->default(fn() => auth()->user()->center_id)
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

            Forms\Components\Fieldset::make('خدمات')
                ->schema([
                    Forms\Components\Checkbox::make('use_printer')->label('خدمة طباعة')->default(false),
                    Forms\Components\Checkbox::make('use_mecrator')->label('خدمة ميكراتور')->default(false),
                    Forms\Components\Checkbox::make('use_drawer')->label('خدمة مرسم')->default(false),
                    Forms\Components\Checkbox::make('use_plotter')->label('خدمة بلوتر')->default(false),
                     Forms\Components\Checkbox::make('use_internet')->label(__('general.labels.internet-service'))->default(false)
                ])
                ->columns(5),
        ])->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('entry_time')
            ->columns([
                TextColumn::make('entry_type')
                    ->label('النوع')
                    ->formatStateUsing(fn($state) => __('general.entry-types.' . $state))
                    ->sortable(),
                TextColumn::make('center.name')->label('المركز')->sortable(),
                TextColumn::make('entry_time')->label('التاريخ')->dateTime('Y-m-d H:i')->sortable(),
                CheckboxColumn::make('use_printer')->label('طباعة')->sortable(),
                CheckboxColumn::make('use_mecrator')->label('ميكراتور')->sortable(),
                CheckboxColumn::make('use_drawer')->label('مرسم')->sortable(),
                CheckboxColumn::make('use_plotter')->label('بلوتر')->sortable(),
                   CheckboxColumn::make('use_internet')->label('انترنت')->sortable()
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->disabled(fn($livewire) => !$livewire->ownerRecord->completed_data)
                    ->tooltip(fn($livewire) => !$livewire->ownerRecord->completed_data
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

                        $livewire->ownerRecord->entries()->create($data);
                    }),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make()
                    ->disabled(fn($record) => !$record->student->completed_data)
                    ->tooltip(fn($record) => !$record->student->completed_data
                        ? '❌ لا يمكن التعديل: بيانات الطالب غير مكتملة'
                        : 'تعديل'),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
