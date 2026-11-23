<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserPermission;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class RolePermission extends Model
{
    use LogsActivity;

    protected $fillable = ['role', 'permissions'];

    protected function casts(): array
    {
        return ['role' => 'integer', 'permissions' => 'array'];
    }

    public static function overridesFor(int $role): array
    {
        return UserPermission::supportsOverrides($role)
            ? (static::query()->where('role', $role)->first()?->permissions ?? [])
            : [];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['role', 'permissions'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
