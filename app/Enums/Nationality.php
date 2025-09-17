<?php

namespace App\Enums;

enum Nationality: string
{
    case Syrian = 'syrian';
    case Other = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Syrian => 'سوري/ة',
            self::Other => 'جنسية أخرى',
        };
    }
}
