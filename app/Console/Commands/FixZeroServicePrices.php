<?php

namespace App\Console\Commands;

use App\Models\EntryService;
use App\Models\LockedMonth;
use App\Models\ServicePrice;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Repairs entry services saved with price 0 by the Filament 5 upgrade bug
 * (the repeater's price was dropped by getState()). Each row gets the price
 * that was active for its center when the row was saved, i.e. what the form showed.
 */
class FixZeroServicePrices extends Command
{
    protected $signature = 'entry-services:fix-zero-prices
        {--since=2026-10-06 : Only rows created on or after this date}
        {--include-locked : Also fix rows whose entry is in a locked month}
        {--force : Apply the changes (without it, only a preview is shown)}';

    protected $description = 'Re-price entry services that were saved with price 0';

    public function handle(): int
    {
        $rows = EntryService::query()
            ->where('price_at_time', 0)
            ->where('created_at', '>=', Carbon::parse($this->option('since'))->startOfDay())
            ->with(['entry:id,center_id,entry_time', 'service:id,name'])
            ->orderBy('id')
            ->get();

        $updates = [];
        $skippedLocked = 0;
        $noPrice = 0;

        foreach ($rows as $row) {
            if (! $row->entry) {
                continue;
            }

            if (! $this->option('include-locked') && LockedMonth::isLocked($row->entry->entry_time)) {
                $skippedLocked++;

                continue;
            }

            $price = $this->priceAt($row->service_id, (int) $row->entry->center_id, $row->created_at);

            if (! $price) {
                $noPrice++;

                continue;
            }

            $updates[$row->id] = [
                'price_at_time' => $price,
                'total' => $price * max(1, (int) $row->amount),
                'label' => $row->service?->name ?? "#{$row->service_id}",
                'entry_time' => $row->entry->entry_time->format('Y-m-d'),
                'amount' => $row->amount,
            ];
        }

        $this->table(
            ['id', 'service', 'entry date', 'amount', 'price', 'total'],
            collect($updates)->map(fn ($u, $id) => [$id, $u['label'], $u['entry_time'], $u['amount'], $u['price_at_time'], $u['total']])->values()->all()
        );

        $this->info('Found: '.$rows->count().' | to fix: '.count($updates)." | skipped (locked month): {$skippedLocked} | no price found: {$noPrice}");

        if (! $this->option('force')) {
            $this->warn('Preview only. Run again with --force to apply.');

            return self::SUCCESS;
        }

        // Query-builder updates on purpose: the locked-month guard lives in the
        // model observer, and --include-locked must be able to bypass it.
        DB::transaction(function () use ($updates) {
            foreach ($updates as $id => $u) {
                EntryService::whereKey($id)->update([
                    'price_at_time' => $u['price_at_time'],
                    'total' => $u['total'],
                ]);
            }
        });

        $this->info('Updated '.count($updates).' rows.');

        return self::SUCCESS;
    }

    /**
     * Price that was in effect for the service in the center at the given moment.
     * Replaced prices get end_date = the new price's start_date, so the range is [start, end).
     */
    private function priceAt(int $serviceId, int $centerId, CarbonInterface $at): ?int
    {
        $base = ServicePrice::query()
            ->where('service_id', $serviceId)
            ->where('center_id', $centerId)
            ->whereNotNull('price');

        $price = (clone $base)
            ->whereDate('start_date', '<=', $at)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>', $at))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->value('price')
            // Fall back to the active price if the history has no matching range.
            ?? (clone $base)->where('is_active', true)->value('price');

        return $price === null ? null : (int) $price;
    }
}
