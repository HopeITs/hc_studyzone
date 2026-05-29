<?php

namespace App\Filament\Resources;

use Filament\Tables\Table;
use Rmsramos\Activitylog\Resources\ActivitylogResource;

class CustomActivitylogResource extends ActivitylogResource
{
    public static function table(Table $table): Table
    {
        // 1. Get the pre-built table from the Rmsramos plugin
        $table = parent::table($table);

        // 2. Grab all the columns
        $columns = $table->getColumns();

        // 3. Target the subject column (usually named 'subject_type')
        $subjectColumnName = 'subject_type';

        if (isset($columns[$subjectColumnName])) {
            // 4. Inject your custom bilingual formatting!
            $columns[$subjectColumnName]->formatStateUsing(function ($record) {

                // Get Arabic Event
                $eventLabel = __('activitylog::action.event.' . $record->event);
                if ($eventLabel === 'activitylog::action.event.' . $record->event) {
                    $eventLabel = ucfirst($record->event);
                }

                // Get Bilingual Subject
                $subjectName = match($record->subject_type) {
                    \App\Models\StudentEntry::class => 'Entry | دخولية',
                    \App\Models\Student::class => 'Student | طالب',
                    \App\Models\StudentEducation::class => 'Education | تعليم',
                    default => class_basename($record->subject_type),
                };

                // Keep it clean with a short UUID
                $shortId = substr($record->subject_id, 0, 8);

                return "{$eventLabel} - {$subjectName} #{$shortId}";
            });
        }

        return $table;
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\CustomActivitylogResource\Pages\ListActivitylogs::route('/'),
            'view'  => \App\Filament\Resources\CustomActivitylogResource\Pages\ViewActivitylog::route('/{record}'),
        ];
    }
}
