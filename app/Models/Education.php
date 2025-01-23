<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Education extends Model
{
    protected $guarded = ['id'];

    public function students():BelongsToMany{
        return $this->belongsToMany(Student::class);
    }
}
