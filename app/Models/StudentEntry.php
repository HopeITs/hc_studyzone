<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Traits\ActivityLog;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;


class StudentEntry extends Model
{
    use HasUuids ,HasFactory,ActivityLog;

    public $incrementing = false; // Disable auto-increment
    protected $keyType = 'string'; // UUIDs are stored as strings
    protected $casts = [
        'entry_time' => 'datetime',
    ];
    protected $guarded = [];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    protected static function booted(): void
    {
        static::updating(function (StudentEntry $entry): void {
            if (($entry->isDirty('center_id') || $entry->isDirty('student_id')
                || ($entry->isDirty('entry_time') && $entry->getOriginal('entry_time')->toDateString() !== $entry->entry_time->toDateString()))
                && $entry->purchasedLines()->whereHas('invoice', fn ($query) => $query->where('status', InvoiceStatus::Paid->value))->exists()) {
                throw ValidationException::withMessages(['entry' => __('pos.errors.entry_has_sales')]);
            }
        });

        // The entry invoice must not outlive its entry: once the entry (and,
        // by cascade, its services) is gone, its money must leave the reports.
        static::deleting(function (StudentEntry $entry): void {
            if ($entry->purchasedLines()->whereHas('invoice', fn ($query) => $query->where('status', InvoiceStatus::Paid->value))->exists()) {
                throw ValidationException::withMessages(['entry' => __('pos.errors.entry_has_sales')]);
            }

            $entry->invoices()
                ->where('status', '!=', InvoiceStatus::Cancelled->value)
                ->get()
                ->each(function (Invoice $invoice): void {
                    $invoice->status = InvoiceStatus::Cancelled;
                    $invoice->save();
                });
        });
    }

    public function delete(): ?bool
    {
        return DB::transaction(fn () => parent::delete(), 3);
    }

    public function purchasedLines(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'visit_id');
    }

    /** The student's entry today at a center (latest, if several). */
    public static function todayFor(string $studentId, int $centerId, bool $lock = false): ?self
    {
        return static::query()
            ->where('student_id', $studentId)
            ->where('center_id', $centerId)
            ->whereDate('entry_time', today())
            ->latest('entry_time')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first();
    }

    /** Invoices generated from this entry's services (see EntryInvoiceService). */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'entry_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(EntryService::class, 'entry_id');
    }

    public function getTotalAmountAttribute(): int
    {
        return $this->services->sum('total');
    }

 public function getServicesListAttribute(): string
{
    return $this->services()
        ->with('service')
        ->get()
        ->groupBy(fn ($item) => $item->service->reference_column_name)
        ->map(function ($group, $referenceColumnName) {
            return match ($referenceColumnName) {
                'use_printer'  => 'طباعة',
                'use_plotter'  => 'بلوتر',
                'use_mecrator'   => 'ميكراتور',
                'use_internet' => 'انترنت',
                'use_drawer' => 'مرسم',
                'reservation' => 'حجز',
                'stationary' => 'قرطاسية',

                default        => $group->first()->service->name,
            };
        })
        ->implode(', ') ?: '-';
}

}

