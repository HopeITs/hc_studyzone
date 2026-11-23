<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Roles;
use App\Models\Center;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class FinanceAccess
{
    public static function allowed(?User $user = null): bool
    {
        $user ??= Auth::user();

        return $user !== null && ($user->role === Roles::GeneralAdmin()
            || ($user->role === Roles::CityAdmin() && $user->city_id !== null));
    }

    /** @return Builder<Center> */
    public static function centerQuery(User $user): Builder
    {
        return Center::query()->when(
            $user->role === Roles::CityAdmin(),
            fn (Builder $query) => $query->where('city_id', $user->city_id),
        );
    }

    public static function canUseCenter(?User $user, int $centerId): bool
    {
        return $user?->role !== Roles::CityAdmin()
            || self::centerQuery($user)->where('is_active', true)->whereKey($centerId)->exists();
    }

    public static function validateCityCenter(User $user, int $centerId): void
    {
        if (! self::canUseCenter($user, $centerId)) {
            throw ValidationException::withMessages([
                'data.center_id' => __('finance.messages.not_allowed'),
            ]);
        }
    }
}
