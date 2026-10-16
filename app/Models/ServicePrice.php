<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePrice extends Model
{
    protected $table = 'service_prices';

    protected $guarded = ['id'];

    protected $casts = [

        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    /**
     * Active price of each service in a center, keyed by service_id.
     * Services without an active price are omitted so callers can detect them.
     *
     * @param  array<int|string>  $serviceIds
     * @return array<int, int>
     */
    public static function activePricesFor(int $centerId, array $serviceIds): array
    {
        if (empty($serviceIds)) {
            return [];
        }

        return static::query()
            ->where('center_id', $centerId)
            ->where('is_active', true)
            ->whereNotNull('price')
            ->whereIn('service_id', $serviceIds)
            ->pluck('price', 'service_id')
            ->map(fn ($price) => (int) $price)
            ->all();
    }

    public function isValid()
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->start_date->isFuture()) {
            return false;
        }

        if ($this->end_date && $this->end_date->isPast()) {
            return false;
        }
    }
}
