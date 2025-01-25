<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Education extends Model
{
    protected $fillable = ['name', 'degree'];
    protected $table = 'educations';

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_educations')->withPivot('level', 'type')->withTimestamps();
    }
}
