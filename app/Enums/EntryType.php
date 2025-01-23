<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Values;

enum EntryType: int
{
    use InvokableCases, Values;

    // Define enum cases with associated integer values
    case Classic  = 0;
    case Dentist  = 1;

    case civil  = 2;




    // Return the default case
    public static function default(): self
    {
        return self::Classic;
    }

    // Return an array of all case values

}
