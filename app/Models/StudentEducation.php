<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentEducation extends Model
{
    protected $guarded = ['id'];
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
    public function education(): BelongsTo{
        return $this->belongsTo(Education::class);
    }
}
