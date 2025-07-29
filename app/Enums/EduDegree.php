<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Values;

enum EduDegree: int
{
    use InvokableCases, Values;

    case MiddleSchool = 4;
    case HighSchool = 5;
    case HigherSecondary = 6;
    case Institution = 7;
    case University = 8;
    case Diploma = 9;
    case Masters = 10;
    case PhD = 11;

    public static function default(): self
    {
        return self::University;
    }
}
