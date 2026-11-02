<?php

declare(strict_types=1);

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Values;

enum PaymentMethod: string
{
    use InvokableCases, Values;

    case Cash = 'cash';

    public static function default(): self
    {
        return self::Cash;
    }
}
