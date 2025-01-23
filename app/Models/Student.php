<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'city_id', 'firstname', 'lastname', 'father_name',
        'national_number', 'phone', 'email', 'gender', 'religion', 'address'
    ];

    public function city(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function entries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StudentEntry::class);
    }

    public function educations(): \Illuminate\Database\Eloquent\Relations\HasManyThrough{
        return $this->hasmanyThrough(Education::class, StudentEntry::class);
    }
}

