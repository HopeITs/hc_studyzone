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

    public function hasRelationManagers(): bool
    {
        return false;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record->getKey()]);
    }
}
