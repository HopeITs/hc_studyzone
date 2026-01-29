<?php

namespace App\Filament\Exports;

use App\Models\Student;
use Carbon\Carbon;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class StudentExporter extends Exporter
{
    protected static ?string $model = Student::class;
    protected static int $maxEducations = 3;

    public static function getEloquentQuery(): Builder
    {
        return Student::query()->with(['street.city', 'educations'])->withCount('entries');
    }

    public function getFormats(): array
    {
        return [
            ExportFormat::Xlsx,
        ];
    }

    public static function getColumns(): array
    {
        $columns = [
            ExportColumn::make('name')
                ->formatStateUsing(fn(Model $record): string => $record->firstname .  ' ' . $record->lastname
                ),
            ExportColumn::make('father_name')
              ,
            ExportColumn::make('national_number')->formatStateUsing(function (Model $record): string {
                if ($record->nationality?->value === 'syrian') {
                    return $record->national_number ?? 'لا يوجد';
                }
                return $record->foreign_id_number ?? 'لا يوجد';
            }),

            ExportColumn::make('street')
                ->label('Street')
                ->formatStateUsing(fn(Model $record): string => optional($record->street)->name ?? '-'
                ),

            ExportColumn::make('city')
                ->label('City')
                ->formatStateUsing(fn(Model $record): string => optional($record->street?->city)->name ?? '-'
                ),

            ExportColumn::make('phone'),
            ExportColumn::make('whatsapp'),
            ExportColumn::make('email'),
            ExportColumn::make('address'),
            ExportColumn::make('entries_count')->counts('entries'),

            ExportColumn::make('gender')
                ->formatStateUsing(fn(string $state): string => __("general.genders.{$state}")
                ),

            ExportColumn::make('religion')
                ->formatStateUsing(fn(string $state): string => __("general.religions.{$state}")
                ),

            ExportColumn::make('year_of_birth')->label('Date of birth')
                ->formatStateUsing(fn(Model $record): string => Carbon::parse($record->year_of_birth)->toDateString()
                ),

            ExportColumn::make('year')->label('Year')
                ->formatStateUsing(fn(Model $record): string => Carbon::parse($record->year_of_birth)->year
                ),

            ExportColumn::make('age')->label('Age')
                ->formatStateUsing(fn(Model $record): string => Carbon::parse($record->year_of_birth)->age
                ),
            ExportColumn::make('created_at')->label('Profile Created at')
                ->formatStateUsing(fn(Model $record): string => Carbon::parse($record->created_at)->toDateString()
                ),

            ExportColumn::make('notes'),
        ];

        for ($i = 0; $i < self::$maxEducations; $i++) {
            $columns[] = ExportColumn::make("education_" . ($i + 1))
                ->label("Education " . ($i + 1))
                ->formatStateUsing(function (Model $record) use ($i) {
                    $education = $record->educations[$i] ?? null;
                    if (!$education) return '-';

                    $name = $education->name ?? '';
                    $level = __('general.edu-levels.' . $education->pivot->level) ?? '';
                    $type = __('general.edu-types.' . $education->pivot->type) ?? '';

                    return "$name ($level, $type)";
                });
        }

        return $columns;
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your student export has completed and ' .
            number_format($export->successful_rows) . ' ' .
            str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' .
                str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
