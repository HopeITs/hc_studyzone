<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Values;

enum EntryPurpose: int
{
    use InvokableCases, Values;

    case Unknown = 0;
    case Studying = 1;
    case Working = 2;
    case Meeting = 3;
    case Coordinator = 4;
    case Participant = 5;
    case Visitor = 6;

    /**
     * Purposes allowed for a given entry type (events / FGD use their own set).
     *
     * @return array<int, int>
     */
    public static function forEntryType(?int $entryType): array
    {
        if (in_array($entryType, [EntryType::Event(), EntryType::FGD()], true)) {
            return [self::Coordinator(), self::Participant(), self::Visitor()];
        }

        return [self::Studying(), self::Working(), self::Meeting()];
    }

    public static function defaultFor(?int $entryType): int
    {
        return self::forEntryType($entryType)[0];
    }

    public static function default(): self
    {
        return self::Studying;
    }
}
