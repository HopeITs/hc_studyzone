<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CashShiftStatus;
use App\Enums\InvoiceStatus;
use App\Traits\ActivityLog;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashShift extends Model
{
    use ActivityLog, HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => CashShiftStatus::class,
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_cash' => 'integer',
        'cash_income' => 'integer',
        'expenses_total' => 'integer',
        'counted_cash' => 'integer',
        'difference' => 'integer',
    ];

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'shift_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'shift_id');
    }

    /** Paid invoices only — cancelled/unpaid money never entered the drawer. */
    protected function computedCashIncome(): Attribute
    {
        return Attribute::make(
            get: fn () => (int) $this->invoices()
                ->where('status', InvoiceStatus::Paid->value)
                ->sum('total'),
        );
    }

    protected function computedExpenses(): Attribute
    {
        return Attribute::make(
            get: fn () => (int) $this->expenses()->sum('amount'),
        );
    }

    /** What should be in the drawer right now. */
    protected function expectedCash(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->opening_cash + $this->computed_cash_income - $this->computed_expenses,
        );
    }

    public function isOpen(): bool
    {
        return $this->status === CashShiftStatus::Open;
    }
}
