<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Values;

enum Religion: int
{
    use InvokableCases, Values;

    // Define enum cases with associated integer values
    case Unknown = 0;
    case Christian = 1;
    case Muslim = 2;


    // Return the default case
    public static function default(): self
    {
        return self::Unknown;
    }

    // Return an array of all case values

}
