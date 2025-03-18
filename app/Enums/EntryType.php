<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Values;

enum EntryType: int
{
    use InvokableCases, Values;

    // Define enum cases with associated integer values
    case Free  = 0;
    case Daily  = 1;
    case Monthly  = 2;




    // Return the default case
    public static function default(): self
    {
        return self::Classic;
    }

    // Return an array of all case values

}
