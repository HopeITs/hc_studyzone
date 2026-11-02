<?php

declare(strict_types=1);

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Values;

enum InventoryUnit: string
{
    use InvokableCases, Values;

    case Piece = 'piece';
    case Box = 'box';
    case Kg = 'kg';
    case Ream = 'ream';
    case Meter = 'meter';

    public static function default(): self
    {
        return self::Piece;
    }
}
