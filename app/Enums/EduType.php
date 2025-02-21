<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Values;

enum EduType: int
{
    use InvokableCases, Values;

    // Define enum cases with associated integer values
    case Governmental  = 0;
    case Private  = 1;
    case Open  = 2;
    case Virtual  = 3;



    // Return the default case
    public static function default(): self
    {
        return self::Governmental;
    }

    // Return an array of all case values

}
