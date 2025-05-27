<?php

namespace App\Models;

use App\Traits\ActivityLog;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class StudentEducation extends Model
{
    use HasUuids, HasFactory,ActivityLog;

    protected $table = 'student_educations';

    public $incrementing = false;
    protected $keyType = 'string';
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
