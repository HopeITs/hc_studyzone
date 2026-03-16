<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Filament\Resources\StudentResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Model;

class CreateStudent extends CreateRecord
{
    protected static string $resource = StudentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['id'] = Str::uuid()->toString();

        $qrContent = json_encode([
            'uuid' => $data['id'],
            'url' => route('student.entry', ['uuid' => $data['id']]),
        ]);

        $fileName = 'qr-codes/' . $data['id'] . '.png';

        $qrCode = QrCode::format('svg')->size(300)->generate($qrContent);
        Storage::disk('public')->put($fileName, $qrCode);

        $data['qr_code'] = $fileName;

        return $data;
    }

    // protected function handleRecordCreation(array $data): Model
    // {
    //     if (isset($data['educations']) && is_array($data['educations']) && count($data['educations']) > 0) {
    //         $educations = collect($data['educations'])->sortBy('start_date')->values();

    //         $hasActiveEducation = false;

    //         foreach ($educations as $index => $edu) {
    //             if (!isset($edu['end_date']) || empty($edu['end_date'])) {
    //                 $hasActiveEducation = true;
    //             }

    //             if ($index < count($educations) - 1 && (!isset($edu['end_date']) || empty($edu['end_date']))) {
    //                 Notification::make()
    //                     ->danger()
    //                     ->title('خطأ في التسلسل الزمني')
    //                     ->body('لا يمكن إضافة دراسة جديدة قبل إنهاء الدراسة السابقة. يجب تحديد تاريخ نهاية للدراسة رقم ' . ($index + 1))
    //                     ->persistent()
    //                     ->send();

    //                 $this->halt();
    //             }

    //             if ($index > 0) {
    //                 $previousEndDate = $educations[$index - 1]['end_date'] ?? null;
    //                 $currentStartDate = $edu['start_date'] ?? null;

    //                 if ($previousEndDate && $currentStartDate && $currentStartDate < $previousEndDate) {
    //                     Notification::make()
    //                         ->danger()
    //                         ->title('خطأ في التسلسل الزمني')
    //                         ->body('تاريخ بداية الدراسة يجب أن يكون بعد تاريخ نهاية الدراسة السابقة')
    //                         ->send();

    //                     $this->halt();
    //                 }
    //             }
    //         }

    //         if ($educations->filter(fn($edu) => !isset($edu['end_date']) || empty($edu['end_date']))->count() > 1) {
    //             Notification::make()
    //                 ->danger()
    //                 ->title('خطأ في التسلسل الزمني')
    //                 ->body('لا يمكن أن يكون هناك أكثر من دراسة نشطة (بدون تاريخ نهاية) في نفس الوقت')
    //                 ->persistent()
    //                 ->send();

    //             $this->halt();
    //         }
    //     }

    //     return parent::handleRecordCreation($data);
    // }

    public function hasRelationManagers(): bool
    {
        return false;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record->getKey()]);
    }
}
