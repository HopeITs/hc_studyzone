<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class StudentEducation extends Pivot
{

    use HasUuids, HasFactory;

    protected $table = 'student_educations';
    public $incrementing = false; // Disable auto-increment
    protected $keyType = 'string'; // UUIDs are stored as strings
    protected $guarded = [];



    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function education(): BelongsTo
    {
        return $this->belongsTo(Education::class);
    }


}
