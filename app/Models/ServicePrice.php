<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePrice extends Model
{
    protected $fillable = ['service_id', 'price', 'is_default', 'start_at', 'end_at'];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'is_default' => 'boolean',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}