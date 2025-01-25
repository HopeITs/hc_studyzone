<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Values;

enum Gender: int
{
    use InvokableCases, Values;

    // Define enum cases with associated integer values
    case Male  = 0;
    case Female  = 1;






    // Return the default case
    public static function default(): self
    {
        return self::Male;
    }

    // Return an array of all case values

}
