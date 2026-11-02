<?php

declare(strict_types=1);

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Values;

enum CashShiftStatus: string
{
    use InvokableCases, Values;

    case Open = 'open';
    case Closed = 'closed';

    public static function default(): self
    {
        return self::Open;
    }
}
