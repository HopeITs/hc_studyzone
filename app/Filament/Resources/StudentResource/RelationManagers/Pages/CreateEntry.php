<?php

namespace App\Filament\Resources\StudentResource\RelationManagers\Pages;

use App\Filament\Resources\StudentResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateEntry extends CreateRecord
{
    protected static string $resource = StudentResource\RelationManagers\EntriesRelationManager::class;

}
