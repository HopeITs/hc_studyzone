<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Values;

enum EduLevel: int
{
    use InvokableCases, Values;

    // Define enum cases with associated integer values

    case First = 1;
    case Second = 2;
    case Third = 3;
    case Forth = 4;
    case Fifth = 5;
    case Sixth = 6;
    case Graduate = 7;

    // Return the default case
    public static function default(): self
    {
        return self::Graduate;
    }

    // Return an array of all case values

}
