<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Traits\ActivityLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use ActivityLog, HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => InvoiceStatus::class,
        'payment_method' => PaymentMethod::class,
        'discount' => 'integer',
        'total' => 'integer',
    ];

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class, 'shift_id');
    }

    /**
     * Next sequential invoice number for a center.
     * Call inside a transaction when creating, so two cashiers
     * cannot take the same number (the unique index is the backstop).
     */
    public static function nextNumberFor(int $centerId): int
    {
        return (int) static::query()
            ->where('center_id', $centerId)
            ->lockForUpdate()
            ->max('number') + 1;
    }

    /** Sum of line totals minus the invoice discount, never below zero. */
    public function computeTotal(): int
    {
        return max(0, (int) $this->items()->sum('line_total') - (int) $this->discount);
    }
}
