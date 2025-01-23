<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class City extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code'];

    public function centers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Center::class);
    }

    public function students(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Student::class);
    }
}
