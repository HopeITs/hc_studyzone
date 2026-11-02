<?php

declare(strict_types=1);

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Values;

enum InvoiceStatus: string
{
    use InvokableCases, Values;

    case Paid = 'paid';
    case Unpaid = 'unpaid';
    case Cancelled = 'cancelled';

    public static function default(): self
    {
        return self::Unpaid;
    }
}
