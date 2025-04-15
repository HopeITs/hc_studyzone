<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\HasTranslations;

class Education extends Model
{
    use HasFactory, HasTranslations;

    protected $table = 'educations';
    protected $translatable = ['name'];
    protected $guarded = ['id'];

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_educations')
            ->withPivot('level', 'type')
            ->withTimestamps();
    }
}
