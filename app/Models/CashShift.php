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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    /** The currently open shift for a center, if any. */
    public static function openShiftForCenter(int $centerId): ?self
    {
        return static::query()
            ->where('center_id', $centerId)
            ->where('status', CashShiftStatus::Open)
            ->latest('opened_at')
            ->first();
    }

    /**
     * Open a new shift for a center. Race-safe: the existing open shift (if any)
     * is locked, so two cashiers cannot both open one for the same center.
     *
     * @throws ValidationException when the center already has an open shift.
     */
    public static function open(int $centerId, int $userId, int $openingCash): self
    {
        return DB::transaction(function () use ($centerId, $userId, $openingCash): self {
            $alreadyOpen = static::query()
                ->where('center_id', $centerId)
                ->where('status', CashShiftStatus::Open)
                ->lockForUpdate()
                ->exists();

            if ($alreadyOpen) {
                throw ValidationException::withMessages([
                    'shift' => __('finance.messages.center_has_open_shift'),
                ]);
            }

            return static::create([
                'center_id' => $centerId,
                'user_id' => $userId,
                'opened_at' => now(),
                'opening_cash' => $openingCash,
                'status' => CashShiftStatus::Open,
            ]);
        });
    }

    /**
     * Close the shift: snapshot the computed income/expenses, record the
     * counted cash and the difference against the expected drawer amount.
     * A closed shift is immutable; a non-zero difference requires a note.
     *
     * @throws ValidationException when already closed, or on an unexplained difference.
     */
    public function close(int $countedCash, ?string $note = null): void
    {
        DB::transaction(function () use ($countedCash, $note): void {
            /** @var self $shift Fresh, locked row so two users cannot close twice. */
            $shift = static::query()->lockForUpdate()->findOrFail($this->id);

            if (! $shift->isOpen()) {
                throw ValidationException::withMessages([
                    'shift' => __('finance.messages.shift_already_closed'),
                ]);
            }

            $cashIncome = $shift->computed_cash_income;
            $expensesTotal = $shift->computed_expenses;
            $expectedCash = $shift->opening_cash + $cashIncome - $expensesTotal;
            $difference = $countedCash - $expectedCash;

            if ($difference !== 0 && ($note === null || trim($note) === '')) {
                throw ValidationException::withMessages([
                    'closing_note' => __('finance.validation.note_required_on_difference'),
                ]);
            }

            $shift->forceFill([
                'cash_income' => $cashIncome,
                'expenses_total' => $expensesTotal,
                'counted_cash' => $countedCash,
                'difference' => $difference,
                'closing_note' => $note,
                'closed_at' => now(),
                'status' => CashShiftStatus::Closed,
            ])->save();

            // Keep the in-memory instance in sync with what was persisted.
            $this->setRawAttributes($shift->getAttributes(), true);
        });
    }
}
