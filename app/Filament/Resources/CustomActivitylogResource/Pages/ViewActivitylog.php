<?php

namespace App\Filament\Resources\CustomActivitylogResource\Pages;

use App\Filament\Resources\CustomActivitylogResource;
use Rmsramos\Activitylog\Resources\Activitylog\Pages\ViewActivitylog as OriginalViewActivitylog;

class ViewActivitylog extends OriginalViewActivitylog
{
    // This forces the view page to use YOUR resource as well
    protected static string $resource = CustomActivitylogResource::class;

    // The vendor page hard-codes getResource() to the base ActivitylogResource
    // instead of returning static::$resource, so it ignores the property above
    // and bypasses CustomActivitylogResource::canAccess(), blocking this page.
    public static function getResource(): string
    {
        return static::$resource;
    }
}
