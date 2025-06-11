<?php

namespace App\Models;

use App\Traits\ActivityLog;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentEntry extends Model
{
    use HasUuids ,HasFactory,ActivityLog;

    public $incrementing = false; // Disable auto-increment
    protected $keyType = 'string'; // UUIDs are stored as strings
    protected $casts = [
        'entry_time' => 'datetime',
    ];
    protected $guarded = [];

    public function student(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function center(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

}

