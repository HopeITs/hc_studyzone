<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use App\Enums\UserPermission;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable,LogsActivity;

    protected static function booted(): void
    {
        static::updating(function (User $user): void {
            if ($user->isDirty('role')) {
                $user->permission_overrides = null;
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName(config('activitylog.default_log_name'))
            ->dontLogIfAttributesChangedOnly(['remember_token']);
        // Chain fluent methods for configuration options
    }
    public function getLogNameToUse(string $eventName = ''): string
    {
        return config('activitylog.default_log_name');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'city_id',
        'center_id',
        'role',
        'is_active',
        'synced_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permission_overrides' => 'array',
        ];
    }

    public function hasPermission(UserPermission $permission): bool
    {
        $role = (int) $this->role;
        $overrides = $this->permission_overrides ?? [];

        if (UserPermission::supportsOverrides($role) && ($overrides['role'] ?? null) === $role) {
            $individual = $overrides['permissions'][$permission->value] ?? null;

            if (is_bool($individual)) {
                return $individual;
            }
        }

        $rolePermissions = once(fn (): array => RolePermission::overridesFor((int) $this->role));

        return $rolePermissions[$permission->value] ?? $permission->defaultFor($role);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    // A User belongs to a Center
    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
    public function canAccessFilament(): bool
    {
        return true;
    }
}
