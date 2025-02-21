<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Values;

enum EduDegree: int
{
    use InvokableCases, Values;

    // Define enum cases with associated integer values
    case MiddleSchool = 0;
    case HighSchool = 1;
    case HigherSecondary = 2;
    case Institution = 3;
    case University = 4;
    case Diploma = 5;
    case Masters = 6;
    case PhD = 7;

    // Return the default case
    public static function default(): self
    {
        return self::University;
    }

    // Return an array of all case values

}
