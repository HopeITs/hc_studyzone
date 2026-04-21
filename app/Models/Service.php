<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Service extends Model
{
    protected $fillable = ['name', 'category_id', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function cityPrices(): HasMany
    {
        return $this->hasMany(CityServicePrice::class);
    }

    public function getPriceForCity(?int $cityId): ?float
    {
        if (!$cityId) {
            return null;
        }
        
        $price = $this->cityPrices()
            ->where('city_id', $cityId)
            ->where('is_active', true)
            ->first();
            
        return $price?->price;
    }
    
    public function getAveragePriceAttribute(): ?float
    {
        $avg = $this->cityPrices()
            ->where('is_active', true)
            ->select(DB::raw('AVG(price) as average'))
            ->value('average');
            
        return $avg ? round($avg, 2) : null;
    }
    
    public function hasPriceForCity(int $cityId): bool
    {
        return $this->cityPrices()
            ->where('city_id', $cityId)
            ->where('is_active', true)
            ->exists();
    }
}