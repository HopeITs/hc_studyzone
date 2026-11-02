<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InventoryUnit;
use App\Enums\StockMovementType;
use App\Traits\ActivityLog;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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

    public function recordPurchase(
        int $quantity,
        int $cost,
        ?string $supplier = null,
        ?string $note = null,
        ?int $userId = null,
    ): StockMovement {
        return DB::transaction(function () use ($quantity, $cost, $supplier, $note, $userId) {
            $movement = $this->movements()->create([
                'center_id' => $this->center_id,
                'user_id' => $userId,
                'type' => StockMovementType::Purchase,
                'quantity' => $quantity,
                'cost' => $cost,
                'note' => $note,
            ]);

            $attributes = [
                'last_purchase_date' => today(),
                // Keep the unit cost of the latest purchase on the item.
                'purchase_cost' => (int) round($cost / max(1, $quantity)),
            ];

            if (is_string($supplier) && trim($supplier) !== '') {
                $attributes['supplier'] = $supplier;
            }

            $this->update($attributes);

            return $movement;
        });
    }

    public function recordCountAdjustment(
        int $countedQuantity,
        string $note,
        ?int $userId = null,
    ): ?StockMovement {
        return DB::transaction(function () use ($countedQuantity, $note, $userId) {
            $difference = $countedQuantity - $this->current_stock;

            if ($difference === 0) {
                return null;
            }

            return $this->movements()->create([
                'center_id' => $this->center_id,
                'user_id' => $userId,
                'type' => StockMovementType::Adjustment,
                'quantity' => $difference,
                'note' => $note,
            ]);
        });
    }
}
