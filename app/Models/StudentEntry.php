<?php

namespace App\Models;

use App\Traits\ActivityLog;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


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

    public function services(): HasMany
    {
        return $this->hasMany(EntryService::class, 'entry_id');
    }

    public function getTotalAmountAttribute(): int
    {
        return $this->services->sum('total');
    }

 public function getServicesListAttribute(): string
{
    return $this->services()
        ->select('service_id')
        ->distinct()
        ->get()
        ->map(function ($service) {
            return $service->service->category->unit === 'paper' ? 'طباعة' : $service->service->name;
        })
        ->unique() // This removes any duplicate strings (like multiple 'طباعة')
        ->implode(', ') ?? '-' ;
}

}

