<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Filament\Resources\StudentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;

class CreateStudent extends CreateRecord
{
    protected static string $resource = StudentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Generate UUID for the student
        $data['id'] = Str::uuid()->toString();

        // Construct the QR code content (UUID and URL)
        $qrContent = json_encode([
            'uuid' => $data['id'],
            'url' => route('student.entry', ['uuid' => $data['id']]),
        ]);

        // Define the file path for the QR code
        $fileName = 'qr-codes/' . $data['id'] . '.png';

        // Generate and store the QR code
        $qrCode = QrCode::format('svg')->size(300)->generate($qrContent);
        Storage::disk('public')->put($fileName, $qrCode);

        // Save the file path to the student's record
        $data['qr_code'] = $fileName;

        return $data;
    }
}
