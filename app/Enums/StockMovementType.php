<?php

declare(strict_types=1);

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Values;

enum StockMovementType: string
{
    use InvokableCases, Values;

    case Purchase = 'purchase';
    case Sale = 'sale';
    case Adjustment = 'adjustment';
    case TeamAllocation = 'team_allocation';
}
