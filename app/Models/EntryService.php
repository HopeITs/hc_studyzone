<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class EntryService extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'pre_finance' => 'boolean',
    ];

    public function save(array $options = []): bool
    {
        return DB::transaction(fn () => parent::save($options), 3);
    }

    public function delete(): ?bool
    {
        return DB::transaction(fn () => parent::delete(), 3);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(StudentEntry::class, 'entry_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** The invoice line that charged this service, if billed. */
    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    /**
     * Sold and paid at the point of sale, then copied onto the entry. Such a
     * row belongs to its POS invoice: entry screens must not edit, delete or
     * re-charge it.
     */
    public function isBilledAtPointOfSale(): bool
    {
        $invoice = $this->invoiceItem?->invoice;

        return $invoice !== null && $invoice->entry_id === null;
    }

    /** Rows the entry screens own (everything not paid at the point of sale). */
    public function scopeNotFromPointOfSale(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('invoice_item_id')
            ->orWhereHas('invoiceItem.invoice', fn (Builder $invoice) => $invoice->whereNotNull('entry_id')));
    }
}
