<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, LogsActivity, HasRoles;

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

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName(config('activitylog.default_log_name'))
            ->dontLogIfAttributesChangedOnly(['remember_token']);
    }

    public function getLogNameToUse(string $eventName = ''): string
    {
        return config('activitylog.default_log_name');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

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
