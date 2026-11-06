<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Roles;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * The single switch for who may touch the financial system at all.
 *
 * For this release only the General Admin may see or use any finance screen,
 * report, widget, print or receipt route. The per-role rules inside each
 * resource (city/center scoping, cashier limits) are kept as the second layer
 * so they can be opened up later by changing only this class.
 */
final class FinanceAccess
{
    public static function allowed(?User $user = null): bool
    {
        $user ??= Auth::user();

        return $user !== null && $user->role === Roles::GeneralAdmin();
    }
}
