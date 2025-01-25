<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentEducation extends Model
{
    use HasUuids;
protected $table = 'student_educations';
    public $incrementing = false; // Disable auto-increment
    protected $keyType = 'string'; // UUIDs are stored as strings
    protected $guarded = ['id'];
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
    public function education(): BelongsTo{
        return $this->belongsTo(Education::class);
    }
}
