<?php

namespace App\Filament\Resources\ServiceResource\RelationManagers;

use App\Enums\Roles;
use App\Models\Center;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class PricesRelationManager extends RelationManager
{
    protected static string $relationship = 'centerPrices';

    public function form(Schema $schema): Schema
    {
        $user = auth()->user();
        $isCenterAdmin = $user->role === Roles::CenterAdmin();

        return $schema
            ->components([
                Select::make('center_id')
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

                TextInput::make('price')
                    ->label(__('general.labels.new_currency_price'))
                    ->numeric()
                    ->suffix('SYP')
                    ->required(),

                DatePicker::make('start_date')
                    ->label(__('general.labels.start_date'))
                    ->required()
                    ->default(now()),

                DatePicker::make('end_date')
                    ->label(__('general.labels.end_date'))
                    ->disabled(fn (Get $get) => $get('is_active'))
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
                TextColumn::make('center.name')
                    ->label(__('general.labels.center'))
                    ->sortable()
                    ->visible($user->role !== Roles::CenterAdmin()), // Hide column if it's always the same center

                TextColumn::make('price')
                    ->label(__('general.services_module.price'))
                    ->suffix(' SYP'),
                TextColumn::make('created_at') // old price
                    ->formatStateUsing(fn (Model $record) => $record->price * 100)
                    ->label(__('general.labels.old_currency_price'))
                    ->suffix(' SYP'),

                IconColumn::make('is_active')
                    ->label(__('general.labels.status'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->sortable(),

                TextColumn::make('start_date')
                    ->label(__('general.labels.start_date'))
                    ->dateTime('Y-m-d')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label(__('general.labels.end_date'))
                    ->dateTime('Y-m-d')
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('center.city.name')
                    ->label(__('general.labels.city'))
                    ->relationship('center.city', 'name')
                    ->searchable()
                    ->columnSpan(1)
                    ->visible($user->role === Roles::GeneralAdmin())
                    ->multiple()
                    ->preload(),

                SelectFilter::make('center_id')
                    ->label(__('general.labels.center'))
                    ->relationship('center', 'name', fn ($query) => $user->role === Roles::CityAdmin() ? $query->where('city_id', $user->city_id) : $query)
                    ->searchable()
                    ->columnSpan($user->role === Roles::GeneralAdmin() ? 1 : 4)
                    ->hidden($user->role === Roles::CenterAdmin())
                    ->multiple()
                    ->preload(),

                TernaryFilter::make('is_active')
                    ->label(__('general.labels.status'))
                    ->placeholder(__('general.labels.all'))
                    ->trueLabel(__('general.labels.active'))
                    ->falseLabel(__('general.labels.inactive')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data) {
                        // Server-side re-check: the submitted center_id is never trusted.
                        $data['center_id'] = $this->enforcedCenterId($data['center_id'] ?? null);

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
            ->recordActions([
                // Price history is immutable for everyone but the GeneralAdmin:
                // editing or deleting past prices would rewrite invoiced history.
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()->role === Roles::GeneralAdmin())
                    ->mutateDataUsing(function (array $data) {
                        // Server-side re-check: the submitted center_id is never trusted.
                        if (array_key_exists('center_id', $data)) {
                            $data['center_id'] = $this->enforcedCenterId($data['center_id']);
                        }

                        return $data;
                    }),
                DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()->role === Roles::GeneralAdmin()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()->role === Roles::GeneralAdmin()),
                ])->visible(fn (): bool => auth()->user()->role === Roles::GeneralAdmin()),
            ]);
    }

    /**
     * The center id a submitted price row may actually be written to:
     * CenterAdmin is always forced to their own center, CityAdmin may only
     * pick centers inside their city, everyone else keeps what they chose.
     *
     * @throws ValidationException
     */
    protected function enforcedCenterId(mixed $centerId): int
    {
        $user = auth()->user();

        if ($user->role === Roles::CenterAdmin()) {
            return (int) $user->center_id;
        }

        if ($user->role === Roles::CityAdmin()) {
            $belongsToCity = Center::whereKey($centerId)
                ->where('city_id', $user->city_id)
                ->exists();

            if (! $belongsToCity) {
                throw ValidationException::withMessages([
                    'center_id' => __('general.validation.center_not_in_city'),
                ]);
            }
        }

        return (int) $centerId;
    }
}
