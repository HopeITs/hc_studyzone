<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CashShiftStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\StockMovementType;
use App\Traits\ActivityLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    /**
     * Collect the invoice: deduct stock, attach the open shift, mark Paid.
     * Throws ValidationException with a translated message on any business refusal.
     */
    public function markPaid(): void
    {
        if (LockedMonth::isLocked(now())) {
            throw ValidationException::withMessages([
                'invoice' => __('invoices.errors.month_locked'),
            ]);
        }

        DB::transaction(function (): void {
            /** @var self $invoice Fresh, locked row so two cashiers cannot collect twice. */
            $invoice = static::query()->lockForUpdate()->findOrFail($this->id);

            if ($invoice->status !== InvoiceStatus::Unpaid) {
                throw ValidationException::withMessages([
                    'invoice' => __('invoices.errors.not_unpaid'),
                ]);
            }

            $lines = $invoice->items()->whereNotNull('inventory_item_id')->with('inventoryItem')->get();

            foreach ($lines as $line) {
                $available = (int) $line->inventoryItem->current_stock;

                if ($line->quantity > $available) {
                    throw ValidationException::withMessages([
                        'invoice' => __('invoices.errors.insufficient_stock', [
                            'item' => $line->inventoryItem->name,
                            'available' => $available,
                        ]),
                    ]);
                }
            }

            foreach ($lines as $line) {
                StockMovement::create([
                    'center_id' => $invoice->center_id,
                    'user_id' => Auth::id(),
                    'inventory_item_id' => $line->inventory_item_id,
                    'type' => StockMovementType::Sale,
                    'quantity' => -$line->quantity,
                    'invoice_id' => $invoice->id,
                    'note' => __('invoices.notes.sale', ['number' => $invoice->number]),
                ]);
            }

            $openShift = CashShift::query()
                ->where('center_id', $invoice->center_id)
                ->where('status', CashShiftStatus::Open->value)
                ->latest('id')
                ->first();

            $invoice->shift_id = $openShift?->id;
            $invoice->status = InvoiceStatus::Paid;
            $invoice->save();

            // Keep the in-memory instance in sync with what was persisted.
            $this->setRawAttributes($invoice->getAttributes(), true);
        });
    }

    /**
     * Cancel the invoice. A paid invoice gets reversing (positive) adjustment
     * movements so the stock log stays append-only and auditable.
     */
    public function markCancelled(): void
    {
        if (LockedMonth::isLocked($this->created_at)) {
            throw ValidationException::withMessages([
                'invoice' => __('invoices.errors.month_locked'),
            ]);
        }

        DB::transaction(function (): void {
            /** @var self $invoice */
            $invoice = static::query()->lockForUpdate()->findOrFail($this->id);

            if ($invoice->status === InvoiceStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'invoice' => __('invoices.errors.already_cancelled'),
                ]);
            }

            if ($invoice->status === InvoiceStatus::Paid) {
                $lines = $invoice->items()->whereNotNull('inventory_item_id')->get();

                foreach ($lines as $line) {
                    StockMovement::create([
                        'center_id' => $invoice->center_id,
                        'user_id' => Auth::id(),
                        'inventory_item_id' => $line->inventory_item_id,
                        'type' => StockMovementType::Adjustment,
                        'quantity' => $line->quantity,
                        'invoice_id' => $invoice->id,
                        'note' => __('invoices.notes.cancellation', ['number' => $invoice->number]),
                    ]);
                }
            }

            $invoice->status = InvoiceStatus::Cancelled;
            $invoice->save();

            $this->setRawAttributes($invoice->getAttributes(), true);
        });
    }
}
