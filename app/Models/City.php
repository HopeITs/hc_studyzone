<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class City extends Model
{

    use HasTranslations;

    protected $translatable = ['name'];
    protected $fillable = ['name', 'code'];

    public function centers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Center::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function streets():HasMany{
        return $this->hasMany(Street::class);
    }
}
