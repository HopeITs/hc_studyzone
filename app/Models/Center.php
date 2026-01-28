<?php

namespace App\Models;

use App\Traits\ActivityLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Center extends Model
{
    use HasFactory  ,ActivityLog;



    protected $casts = [
    'name' => 'array',
];

    protected $guarded = ['id'];

    public function city(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function entries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StudentEntry::class);
    }

    public function users(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(User::class);
    }
}
