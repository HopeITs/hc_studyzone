<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentEntry extends Model
{
    use HasFactory;

    protected $fillable = ['student_id', 'user_id', 'center_id', 'entry_type', 'entry_time'];

    public function student(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function center(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Center::class);
    }
}

