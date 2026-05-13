<?php

namespace App\Filament\Resources\ServiceResource\RelationManagers;

use App\Models\Center;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use App\Enums\Roles;
use Filament\Tables\Filters\TernaryFilter;
class PricesRelationManager extends RelationManager
{
    protected static string $relationship = 'centerPrices';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('center_id')
                    ->label(__('general.labels.center'))
                    ->options(function () {
                        return Center::where('is_active', 1)
                            ->when(auth()->user()->city_id, fn ($query, $cityId) => $query->where('city_id', $cityId))
                            ->get()
                            ->pluck('name', 'id');
                    })
                    ->required()
                    ->searchable(),

                Forms\Components\TextInput::make('price')
                    ->label(__('general.labels.price'))
                    ->numeric()
                    ->suffix('SYP')
                    ->required(),

                Forms\Components\DatePicker::make('start_date')
                    ->label(__('general.labels.start_date'))
                    ->required()
                    ->default(now()),

                Forms\Components\DatePicker::make('end_date')
                    ->label(__('general.labels.end_date'))
                    ->disabled(fn (Forms\Get $get) => $get('is_active'))
                    ->dehydrated(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table

            ->columns([
                Tables\Columns\TextColumn::make('center.name')
                    ->label(__('general.labels.center'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label(__('general.labels.price'))
                    ->suffix(' SYP'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('general.labels.status'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->label(__('general.labels.start_date'))
                    ->dateTime('Y-m-d')->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label(__('general.labels.end_date'))
                    ->dateTime('Y-m-d')
                    ->placeholder('-'),
            ])
            ->filters([
                  SelectFilter::make('center.city.name')
                    ->label('المدينة')
                    ->relationship('center.city', 'name')
                    ->searchable()
                    ->columnSpan(1)
                    ->visible(auth()->user()->role === Roles::GeneralAdmin())
                    ->multiple()
                    ->preload(),
                SelectFilter::make('center_id')
                    ->label('المركز')
                    ->relationship('center', 'name')
                    ->searchable()
                    ->columnSpan(auth()->user()->role === Roles::GeneralAdmin() ? 1 : 4)
                    ->hidden(auth()->user()->role === Roles::CenterAdmin())
                    ->multiple()
                    ->preload(),
                     TernaryFilter::make('is_active')

                    ->placeholder('الكل')
                    ->trueLabel('نعم')
                    ->falseLabel('لا')
            
                    ->columnSpan(1),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data) {
                        // Deactivate previous active price for this center
                        // and set its end_date to the new price's start_date
                        $this->getRelationship()->getQuery()
                            ->where('service_id', $this->getOwnerRecord()->id)
                            ->where('center_id', $data['center_id'])
                            ->where('is_active', true)
                            ->update([
                                'is_active' => false,
                                'end_date' => $data['start_date'],
                            ]);

                        // Ensure new record is active
                        $data['is_active'] = true;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

}
