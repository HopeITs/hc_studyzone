<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntryService extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['entry_id', 'service_id', 'price_at_time'];

    protected $casts = [
        'price_at_time' => 'decimal:2',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(StudentEntry::class, 'entry_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}