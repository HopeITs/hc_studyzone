<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Values;

enum Gender: int
{
    use InvokableCases, Values;

    // Define enum cases with associated integer values
    case Unkonwn = 0;
    case Male  = 1;
    case Female  = 2;






    // Return the default case
    public static function default(): self
    {
        return self::Male;
    }

    // Return an array of all case values


}
