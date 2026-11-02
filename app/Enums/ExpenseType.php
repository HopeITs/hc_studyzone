<?php

declare(strict_types=1);

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Values;

enum ExpenseType: string
{
    use InvokableCases, Values;

    case Operations = 'operations';
    case Maintenance = 'maintenance';
    case WarehousePurchase = 'warehouse_purchase';
    case Transport = 'transport';
    case Hospitality = 'hospitality';
    case Advance = 'advance';
    case Other = 'other';
}
