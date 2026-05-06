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
    case Course = 3;
    case Hall = 4;
    case Event = 5;
    case FGT = 6 ;

    // Return the default case
    public static function default(): self
    {
        return self::Daily;
    }

    // Return an array of all case values

}
