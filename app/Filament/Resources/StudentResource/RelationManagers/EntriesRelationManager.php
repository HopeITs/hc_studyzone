<?php

namespace App\Filament\Resources\StudentResource\RelationManagers;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Forms;
use Illuminate\Support\Facades\Auth;
use App\Filament\Resources\StudentResource\RelationManagers\Pages;
class EntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'entries';



    public function form(Form $form): Form
    {
        $user = Auth::user();
        $isCenterAdmin = $user->hasRole('center_admin');
        return $form->schema([

            Forms\Components\Hidden::make('user_id')
                ->default($user->id), // ✅ Always set center_id

            Forms\Components\Select::make('center_id')
                ->relationship('center', 'name')
                ->default($isCenterAdmin ? $user->center_id : null)
                ->required()->columnSpanFull(),
            Forms\Components\Select::make('entry_type')
                ->options(__('general.entry-types'))
                ->default(0)
                ->required()->columnSpanFull(),
            Forms\Components\DateTimePicker::make('entry_time')
                ->default(now())
                ->required()->columnSpanFull(),
            Fieldset::make('Services')->label('خدمات')
                ->schema([
                    Checkbox::make('use_printer')->reactive()
                        ->label('خدمة طباعة')
                        ->default(false)
                    ,
                    Checkbox::make('use_drawer')->reactive()
                        ->label('خدمة مرسم')
                        ->default(false)
                    ,
                    Checkbox::make('use_mecrator')->reactive()
                        ->label('خدمة ميكراتور')
                        ->default(false)
                    ,
                ])->columns(3)
        ]);
    }


    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('entry_type')->label('Type')
                ->formatStateUsing(fn(string $state): string => __("general.entry-types.{$state}"))
                ->sortable(),

            TextColumn::make('entry_time')->label('Date')->date()->sortable(),

        ])
            ->headerActions([
//                CreateAction::make(),
                CreateAction::make()

                    ->openUrlInNewTab(),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),

            ]);
    }

    public static function getPages(): array
    {
        return [
            'create' => Pages\CreateEntry::route('/create'),
            'edit' => Pages\EditEntry::route('/{record}/edit'),
        ];
    }


}
