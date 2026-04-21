<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CityServicePrice extends Model
{
    protected $table = 'city_service_prices';
    
    protected $fillable = [
        'service_id',
        'city_id',
        'price',
        'is_active',
    ];
    
    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];
    
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
    
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}