<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Values;

enum EntryPurpose: int
{
    use InvokableCases, Values;

    case Unknown = 0;
    case Studying = 1;
    case Working = 2;

    public static function default(): self
    {
        return self::Studying;
    }
}
