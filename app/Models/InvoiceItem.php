<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\ActivityLog;
use App\Traits\GuardsLockedMonth;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class InvoiceItem extends Model
{
    use ActivityLog, GuardsLockedMonth, HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'integer',
        'line_total' => 'integer',
    ];

    protected static function booted(): void
    {
        // Mirrors the DB CHECK constraint for drivers that ignore it:
        // a line sells exactly one of inventory item / service.
        static::saving(function (InvoiceItem $item) {
            if (is_null($item->inventory_item_id) === is_null($item->service_id)) {
                throw new InvalidArgumentException(
                    'An invoice line must reference exactly one of: inventory item or service.'
                );
            }
        });
    }

    /**
     * A line is locked by the month of its parent invoice. On the POS /
     * create-invoice happy path the invoice was just created with now(),
     * so an open current month lets the lines through.
     */
    protected function lockedMonthDates(bool $original = false): array
    {
        return [$this->invoice?->created_at ?? now()];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
