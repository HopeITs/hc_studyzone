<?php

namespace App\Filament\Resources\CustomActivitylogResource\Pages;

use App\Filament\Resources\CustomActivitylogResource;
use Rmsramos\Activitylog\Resources\ActivitylogResource\Pages\ViewActivitylog as OriginalViewActivitylog;

class ViewActivitylog extends OriginalViewActivitylog
{
    // This forces the view page to use YOUR resource as well
    protected static string $resource = CustomActivitylogResource::class;
}
