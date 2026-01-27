<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Values;

enum Roles: int
{
    use InvokableCases, Values;

    case Unknown      = 0;
    case GeneralAdmin = 1;
    case CityAdmin    = 2;
    case CenterAdmin  = 3;
    case MealAdmin    = 4;
    case Observer     = 5;

    public static function default(): self
    {
        return self::Unknown;
    }

    public function label(): string
    {
        return match ($this) {
            self::Unknown      => 'Unknown',
            self::GeneralAdmin => 'General Admin',
            self::CityAdmin    => 'City Admin',
            self::CenterAdmin  => 'Center Admin',
            self::MealAdmin  => 'MEAL Admin',
            self::Observer     => 'Observer',
        };
    }

    public static function labels(): array
    {
        return array_map(
            fn (Roles $role) => $role->label(),
            self::cases()
        );
    }
}
