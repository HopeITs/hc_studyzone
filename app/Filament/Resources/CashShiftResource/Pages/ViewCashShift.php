<?php

namespace App\Filament\Resources\CashShiftResource\Pages;

use App\Filament\Resources\CashShiftResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCashShift extends ViewRecord
{
    protected static string $resource = CashShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CashShiftResource::closeShiftAction(),

            CashShiftResource::printReportAction(),
        ];
    }
}
