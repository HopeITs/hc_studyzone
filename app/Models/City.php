<?php

namespace App\Models;

use App\Traits\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Spatie\Translatable\HasTranslations;

class City extends Model
{

    use HasTranslations,ActivityLog;

    protected $translatable = ['name'];
    protected $guarded = ['id'];

    public function centers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Center::class);
    }

    public function students(): HasManyThrough
    {
        return $this->hasManyThrough(Student::class , Street::class);
    }

    public function streets():HasMany{
        return $this->hasMany(Street::class);
    }
}
