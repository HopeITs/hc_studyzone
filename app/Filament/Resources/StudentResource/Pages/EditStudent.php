<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Filament\Resources\StudentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditStudent extends EditRecord
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($data['is_child']) {
            $data['national_number'] = null;
        }

        if (!isset($data['skip_national_number'])) {
            $data['skip_national_number'] = false;
        }

        return $data;
    }

    // protected function beforeSave(): void
    // {
    //     $data = $this->data;

    //     if (isset($data['educations']) && is_array($data['educations']) && count($data['educations']) > 0) {
    //         $educations = collect($data['educations'])
    //             ->filter(fn($edu) => isset($edu['start_date']))
    //             ->sortBy('start_date')
    //             ->values();

    //         if ($educations->count() > 0) {
    //             $hasActiveEducation = false;

    //             foreach ($educations as $index => $edu) {
    //                 if (!isset($edu['end_date']) || empty($edu['end_date'])) {
    //                     $hasActiveEducation = true;
    //                 }

    //                 if ($index < count($educations) - 1 && (!isset($edu['end_date']) || empty($edu['end_date']))) {
    //                     Notification::make()
    //                         ->danger()
    //                         ->title('خطأ في التسلسل الزمني')
    //                         ->body('لا يمكن إضافة دراسة جديدة قبل إنهاء الدراسة السابقة. يجب تحديد تاريخ نهاية للدراسة رقم ' . ($index + 1))
    //                         ->persistent()
    //                         ->send();

    //                     $this->halt();
    //                 }

    //                 if ($index > 0) {
    //                     $previousEndDate = $educations[$index - 1]['end_date'] ?? null;
    //                     $currentStartDate = $edu['start_date'] ?? null;

    //                     if ($previousEndDate && $currentStartDate && $currentStartDate < $previousEndDate) {
    //                         Notification::make()
    //                             ->danger()
    //                             ->title('خطأ في التسلسل الزمني')
    //                             ->body('تاريخ بداية الدراسة يجب أن يكون بعد تاريخ نهاية الدراسة السابقة')
    //                             ->send();

    //                         $this->halt();
    //                     }
    //                 }
    //             }

    //             if ($educations->filter(fn($edu) => !isset($edu['end_date']) || empty($edu['end_date']))->count() > 1) {
    //                 Notification::make()
    //                     ->danger()
    //                     ->title('خطأ في التسلسل الزمني')
    //                     ->body('لا يمكن أن يكون هناك أكثر من دراسة نشطة (بدون تاريخ نهاية) في نفس الوقت')
    //                     ->persistent()
    //                     ->send();

    //                 $this->halt();
    //             }
    //         }
    //     }
    // }
}
