<?php

namespace App\Filament\Resources\CustomActivitylogResource\Pages;

use App\Filament\Resources\CustomActivitylogResource;
use Rmsramos\Activitylog\Resources\Activitylog\Pages\ListActivitylog;

class ListActivitylogs extends ListActivitylog
{
    // This forces the page to use YOUR resource, not the original one!
    protected static string $resource = CustomActivitylogResource::class;
}
