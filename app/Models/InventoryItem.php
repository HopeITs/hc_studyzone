<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InventoryUnit;
use App\Traits\ActivityLog;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use ActivityLog, HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'unit' => InventoryUnit::class,
        'purchase_cost' => 'integer',
        'sale_price' => 'integer',
        'min_stock_threshold' => 'integer',
        'last_purchase_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    // Stock is never stored: it is always the sum of signed movements,
    // so the movement log stays the single source of truth.
    protected function currentStock(): Attribute
    {
        return Attribute::make(
            get: fn () => (int) $this->movements()->sum('quantity'),
        );
    }

    public function isBelowThreshold(): bool
    {
        return $this->current_stock <= $this->min_stock_threshold;
    }
}
