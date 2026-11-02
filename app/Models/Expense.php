<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExpenseType;
use App\Traits\ActivityLog;
use App\Traits\GuardsLockedMonth;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expense extends Model
{
    use ActivityLog, GuardsLockedMonth, HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'type' => ExpenseType::class,
        'amount' => 'integer',
        'date' => 'date',
    ];

    protected static function booted(): void
    {
        // Delete linked movements through the models (not the query builder)
        // so activity logging and sync replay see each deletion. The DB
        // cascade stays as a backstop.
        static::deleting(fn (Expense $expense) => $expense->stockMovements()->get()->each->delete());
    }

    /** An expense is locked by the month of its own date, not created_at. */
    protected function lockedMonthDateColumns(): array
    {
        return ['date'];
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class, 'shift_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
