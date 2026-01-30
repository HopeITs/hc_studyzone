<?php

namespace App\Filament\Exports;

use App\Models\Student;
use App\Models\StudentEntry;
use Carbon\Carbon;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class EntryExporter extends Exporter
{
    protected static ?string $model = StudentEntry::class;
    protected static int $maxEducations = 1;

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
                ->formatStateUsing(fn(Model $record): string => $record->student->firstname . ' ' . $record->student->lastname),
            ExportColumn::make('father_name')
                ->formatStateUsing(fn(Model $record): string => $record->student->father_name ?? '-'),
            ExportColumn::make('mobile')
                ->formatStateUsing(fn(Model $record): string => $record->student->phone ?? '-'),
            ExportColumn::make('whatsapp')
                ->formatStateUsing(fn(Model $record): string => $record->student->whatsapp ?? '-'),
            ExportColumn::make('national_number')
                ->formatStateUsing(function (Model $record): string {
                    if ($record->student->nationality?->value === 'syrian') {
                        return $record->student->national_number ?? 'لا يوجد';
                    }
                    return $record->student->foreign_id_number ?? 'لا يوجد';
                }),
            ExportColumn::make('religion')
                ->formatStateUsing(fn(Model $record): string => __("general.religions.{$record->student->religion}") ?? '-'
                ),
            ExportColumn::make('entry_type')
                ->formatStateUsing(fn(string $state): string => __("general.entry-types.{$state}")
                ),
            ExportColumn::make('educations')
                ->label('Education')
                ->formatStateUsing(function ($record) {
                    return $record->student->educations->take(3)->pluck('name')->implode(', ');
                }),

            ExportColumn::make('city')
                ->label('City')
                ->formatStateUsing(fn(Model $record): string => optional($record->student->street?->city)->name ?? '-'
                ),
            ExportColumn::make('center')
                ->label('Center')
                ->formatStateUsing(fn(Model $record): string => optional($record->center)->name['ar'] ?? '-'
                ),

            ExportColumn::make('use_printer')->formatStateUsing(fn(string $state): string => $state ? 'نعم' : '-'),
            ExportColumn::make('use_mecrator')->formatStateUsing(fn(string $state): string => $state ? 'نعم' : '-'),
            ExportColumn::make('use_drawer')->formatStateUsing(fn(string $state): string => $state ? 'نعم' : '-'),
            ExportColumn::make('entry_time'),

        ];


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
