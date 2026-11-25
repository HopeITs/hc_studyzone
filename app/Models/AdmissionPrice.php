<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EntryType;
use App\Traits\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdmissionPrice extends Model
{
    use ActivityLog;

    protected $fillable = ['center_id', 'entry_type', 'amount', 'starts_on', 'ends_on'];

    public static function dailyFeeOn(int $centerId, string $date): ?int
    {
        $amount = static::query()
            ->where('center_id', $centerId)
            ->where('entry_type', EntryType::Daily())
            ->where('starts_on', '<=', $date)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $date))
            ->latest('starts_on')
            ->value('amount');

        return $amount === null ? null : (int) $amount;
    }

    public static function schedule(int $centerId, EntryType $type, int $amount, string $start, ?string $end): void
    {
        DB::transaction(function () use ($centerId, $type, $amount, $start, $end): void {
            Center::query()->lockForUpdate()->findOrFail($centerId);

            $previous = static::query()
                ->where('center_id', $centerId)
                ->where('entry_type', $type->value)
                ->latest('starts_on')
                ->first();

            if ($previous && ($start <= $previous->starts_on || ($previous->ends_on && $start <= $previous->ends_on))) {
                throw ValidationException::withMessages(['mountedActions.0.data.start' => __('general.admission_preview.overlap')]);
            }

            if ($previous && $previous->ends_on === null) {
                $previous->update(['ends_on' => Carbon::parse($start)->subDay()->toDateString()]);
            }

            static::query()->create([
                'center_id' => $centerId,
                'entry_type' => $type->value,
                'amount' => $amount,
                'starts_on' => $start,
                'ends_on' => $end,
            ]);
        }, 3);
    }
}
