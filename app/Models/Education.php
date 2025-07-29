<?php

namespace App\Models;

use App\Enums\EduDegree;
use App\Traits\ActivityLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\HasTranslations;

class Education extends Model
{
    use HasFactory, HasTranslations, ActivityLog;

    protected $table = 'educations';
    protected $translatable = ['name'];
    protected $guarded = ['id'];

    protected $casts = [
        'degree' => EduDegree::class,
    ];

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_educations')->withPivot('level', 'type')->withTimestamps();
    }
}
