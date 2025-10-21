<?php

namespace App\Models;

use App\Traits\ActivityLog;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Enums\Nationality;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model implements HasName
{
    use HasUuids, HasFactory, ActivityLog;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = ['is_child', 'city_id'];
    protected $primaryKey = 'id';

    protected $casts = [
        'nationality' => Nationality::class,
        'national_number' => 'string',
        'foreign_id_number' => 'string',
    ];

    public function entries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StudentEntry::class);
    }

    public function educations(): BelongsToMany
    {
        return $this->belongsToMany(Education::class, 'student_educations')
            ->withPivot(['level', 'type'])
            ->withTimestamps();
    }

    public function street(): BelongsTo
    {
        return $this->belongsTo(Street::class);
    }

    public function studentEducations(): HasMany
    {
        return $this->hasMany(StudentEducation::class, 'student_id');
    }

    public function getQrCodePath(): string
    {
        return $this->qr_code ?? '';
    }

    public function getFilamentName(): string
    {
        return "{$this->firstname} {$this->father_name} {$this->lastname}";
    }
}
