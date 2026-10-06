<?php

namespace App\Filament\Resources\SocialLinkResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\SocialLinkResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSocialLink extends EditRecord
{
    protected static string $resource = SocialLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
