<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{

    use HasUuids , HasFactory;

    public $incrementing = false; // Disable auto-increment
    protected $keyType = 'string'; // UUIDs are stored as strings
    protected $fillable = [
       'id', 'city_id', 'firstname', 'lastname', 'father_name',
        'national_number', 'phone', 'email', 'gender', 'religion', 'address' , 'synced_at'
    ];

    public function city(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(City::class);
    }

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


    public function studentEducations(): HasMany{
        return $this->hasMany(StudentEducation::class);
    }
}

