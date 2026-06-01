<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Casts\Attribute;

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

    public function centerPrices(): HasMany
    {
        return $this->hasMany(ServicePrice::class);
    }

    public function getPriceForCenter(?int $centerId): ?float
    {
        if (! $centerId) {
            return null;
        }

        $price = $this->centerPrices()
            ->where('center_id', $centerId)
            ->where('is_active', true)
            ->first();

        return $price?->price;
    }

    public function getPriceRangeForCity(int $cityId): ?object
    {
        return $this->centerPrices()
            ->where('is_active', true)
            ->whereHas('center', fn($q) => $q->where('city_id', $cityId))
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->first();
    }

    public function getAveragePriceAttribute(): ?float
    {
        $avg = $this->centerPrices()
            ->where('is_active', true)
            ->select(DB::raw('AVG(price) as average'))
            ->value('average');

        return $avg ? round($avg, 2) : null;
    }

    public function hasPriceForCenter(int $centerId): bool
    {
        return $this->centerPrices()
            ->where('center_id', $centerId)
            ->where('is_active', true)
            ->exists();
    }

    protected function price(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->centerPrices()->whereNull('end_date')->where('is_active', 1)->first()?->price,
        );
    }
}
