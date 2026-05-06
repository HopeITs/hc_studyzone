<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CenterServicePrice extends Model
{
    protected $table = 'center_service_prices';

    protected $fillable = [
        'service_id',
        'center_id',
        'price',
        'is_active',
    ];

    protected $casts = [

        'is_active' => 'boolean',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }
}
