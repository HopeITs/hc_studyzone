<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{

    use HasUuids , HasFactory;

    public $incrementing = false; // Disable auto-increment
    protected $keyType = 'string'; // UUIDs are stored as strings
    protected $guarded = [];



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

    public function street(): BelongsTo{
        return $this->belongsTo(Street::class);
    }


    public function studentEducations(): HasMany{
        return $this->hasMany(StudentEducation::class);
    }
}

