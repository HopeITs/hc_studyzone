<?php

namespace App\Filament\Resources\ServiceResource\RelationManagers;

use App\Enums\Roles;
use App\Models\Center;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PricesRelationManager extends RelationManager
{
    protected static string $relationship = 'centerPrices';

    public function form(Form $form): Form
    {
        $user = auth()->user();
        $isCenterAdmin = $user->role === Roles::CenterAdmin();

        return $form
            ->schema([
                Forms\Components\Select::make('center_id')
                    ->label(__('general.labels.center'))
                    ->options(function () use ($user) {
                        $query = Center::where('is_active', 1);

                        if ($user->role === Roles::CityAdmin()) {
                            $query->where('city_id', $user->city_id);
                        } elseif ($user->role === Roles::CenterAdmin()) {
                            $query->where('id', $user->center_id);
                        }

                        return $query->pluck('name', 'id');
                    })
                    ->required()
                    ->searchable()
                    ->default($isCenterAdmin ? $user->center_id : null)
                    ->disabled($isCenterAdmin) // Center admin cannot change the center
                    ->dehydrated(), // Ensure disabled value is still saved

                Forms\Components\TextInput::make('price')
                    ->label('السعر (عملة جديدة)')
                    ->numeric()
                    ->suffix('SYP')
                    ->required(),

                Forms\Components\DatePicker::make('start_date')
                    ->label('تاريخ البداية')
                    ->required()
                    ->default(now()),

                Forms\Components\DatePicker::make('end_date')
                    ->label('تاريخ النهاية')
                    ->disabled(fn (Forms\Get $get) => $get('is_active'))
                    ->dehydrated(),
            ]);
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($user) {
                // Restrict the query based on the user's role
                if ($user->role === Roles::CityAdmin()) {
                    $query->whereHas('center', fn ($q) => $q->where('city_id', $user->city_id));
                } elseif ($user->role === Roles::CenterAdmin()) {
                    $query->where('center_id', $user->center_id);
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('center.name')
                    ->label(__('general.labels.center'))
                    ->sortable()
                    ->visible($user->role !== Roles::CenterAdmin()), // Hide column if it's always the same center

                Tables\Columns\TextColumn::make('price')
                    ->label('السعر')
                    ->suffix(' SYP'),
                Tables\Columns\TextColumn::make('created_at') //old price
                    ->formatStateUsing(fn (Model $record) => $record->price*100)
                    ->label('السعر (عملة قديمة)')
                    ->suffix(' SYP'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('الحالة')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('تاريخ البداية')
                    ->dateTime('Y-m-d')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('تاريخ النهاية')
                    ->dateTime('Y-m-d')
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('center.city.name')
                    ->label('المدينة')
                    ->relationship('center.city', 'name')
                    ->searchable()
                    ->columnSpan(1)
                    ->visible($user->role === Roles::GeneralAdmin())
                    ->multiple()
                    ->preload(),

                SelectFilter::make('center_id')
                    ->label('المركز')
                    ->relationship('center', 'name', fn ($query) => $user->role === Roles::CityAdmin() ? $query->where('city_id', $user->city_id) : $query)
                    ->searchable()
                    ->columnSpan($user->role === Roles::GeneralAdmin() ? 1 : 4)
                    ->hidden($user->role === Roles::CenterAdmin())
                    ->multiple()
                    ->preload(),

                TernaryFilter::make('is_active')
                ->label('الحالة')
                    ->placeholder('الكل')
                    ->trueLabel('فعالة')
                    ->falseLabel('غير فعال')
                  ,
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data) {
                        // Deactivate previous active price for this center
                        $this->getRelationship()->getQuery()
                            ->where('service_id', $this->getOwnerRecord()->id)
                            ->where('center_id', $data['center_id'])
                            ->where('is_active', true)
                            ->update([
                                'is_active' => false,
                                'end_date' => $data['start_date'],
                            ]);

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
