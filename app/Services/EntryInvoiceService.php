<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\CashShift;
use App\Models\EntryService;
use App\Models\Invoice;
use App\Models\LockedMonth;
use App\Models\Student;
use App\Models\StudentEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Keeps student entries and invoices in step, so every paid service is
 * counted exactly once in the finance reports:
 *
 * - Services added from the entry screens are charged on one paid
 *   "entry invoice" per entry (invoices.entry_id), rebuilt by sync().
 * - Services sold at the point of sale to a student who has an entry today
 *   are copied onto that entry, already linked to the POS invoice line, so
 *   sync() never charges them a second time (linkPaidInvoice()).
 */
class EntryInvoiceService
{
    public const DRINK_CATEGORY = 'مشروبات';

    public function assertDailyServiceAvailable(EntryService $row): void
    {
        $service = $row->service()->with('category')->first();

        if ($service?->category?->unit !== 'day') {
            return;
        }

        if ((int) $row->amount !== 1) {
            throw ValidationException::withMessages(['invoice' => __('pos.errors.daily_service_quantity')]);
        }

        $entry = StudentEntry::query()->findOrFail($row->entry_id);
        $this->lockStudent($entry->student_id);
        $exists = EntryService::query()
            ->where('service_id', $row->service_id)
            ->when($row->exists, fn ($query) => $query->whereKeyNot($row->getKey()))
            ->whereHas('entry', fn ($query) => $query
                ->where('student_id', $entry->student_id)
                ->where('center_id', $entry->center_id)
                ->whereDate('entry_time', $entry->entry_time))
            ->lockForUpdate()
            ->first(['id']) !== null;

        if ($exists) {
            throw ValidationException::withMessages([
                'invoice' => __('pos.errors.already_on_entry', ['service' => $service->name]),
            ]);
        }
    }

    /**
     * Rebuild the entry invoice from the entry's own services. Safe to call
     * any number of times: it only adds, updates or removes what changed.
     */
    public function sync(StudentEntry|string $entry): void
    {
        $entryId = $entry instanceof StudentEntry ? $entry->getKey() : $entry;

        DB::transaction(function () use ($entryId): void {
            $entry = StudentEntry::query()->lockForUpdate()->find($entryId);

            if ($entry === null) {
                return;
            }

            $openInvoices = Invoice::query()
                ->where('entry_id', $entry->id)
                ->where('status', '!=', InvoiceStatus::Cancelled->value)
                ->lockForUpdate()
                ->get();

            // An entry moved to another center gets a fresh invoice there.
            $current = $openInvoices->firstWhere('center_id', (int) $entry->center_id);

            $openInvoices
                ->reject(fn (Invoice $invoice) => $invoice->is($current))
                ->each(fn (Invoice $invoice) => $this->cancelQuietly($invoice));

            $own = $this->ownServices($entry);

            if ($own->isEmpty()) {
                if ($current !== null) {
                    $current->items()->get()->each->delete();
                    $current->total = 0;
                    $this->cancelQuietly($current);
                }

                return;
            }

            $current ??= $this->createEntryInvoice($entry);

            $keptItemIds = [];

            foreach ($own as $service) {
                $item = $service->invoice_item_id !== null
                    ? $current->items()->find($service->invoice_item_id)
                    : null;

                $values = [
                    'service_id' => $service->service_id,
                    'quantity' => (int) $service->amount,
                    'unit_price' => (int) $service->price_at_time,
                    'line_total' => (int) $service->total,
                ];

                if ($item === null) {
                    $item = $current->items()->create($values);

                    // Query update: no observer, no recursion back into sync().
                    EntryService::query()->whereKey($service->id)->update(['invoice_item_id' => $item->id]);
                } else {
                    $item->fill($values);

                    if ($item->isDirty()) {
                        $item->save();
                    }
                }

                $keptItemIds[] = $item->id;
            }

            // Lines whose service was removed from the entry.
            $current->items()->whereNotIn('id', $keptItemIds)->get()->each->delete();

            $total = $current->computeTotal();

            if ((int) $current->total !== $total) {
                $current->total = $total;
                $current->save();
            }
        });
    }

    /**
     * Called when a point-of-sale invoice is paid: copy its service lines
     * onto the student's entry of today, linked to the paid lines. Refuses a
     * daily service (e.g. internet) the entry already has, so a student is
     * never charged twice for the same day.
     *
     * @throws ValidationException
     */
    public function linkPaidInvoice(Invoice $invoice): void
    {
        if ($invoice->entry_id !== null || $invoice->student_id === null) {
            return;
        }

        $this->lockStudent($invoice->student_id);
        $entry = StudentEntry::todayFor((string) $invoice->student_id, (int) $invoice->center_id, lock: true);

        if ($entry === null) {
            return;
        }

        $lines = $invoice->items()
            ->with('service.category')
            ->get();

        if ($lines->isEmpty()) {
            return;
        }

        $existingServiceIds = EntryService::query()
            ->whereHas('entry', fn ($query) => $query
                ->where('student_id', $invoice->student_id)
                ->where('center_id', $invoice->center_id)
                ->whereDate('entry_time', today()))
            ->lockForUpdate()
            ->pluck('service_id')->map(fn ($id) => (int) $id)->all();
        $referenceColumns = [];

        foreach ($lines as $line) {
            $service = $line->service;

            if (LockedMonth::isLocked($entry->entry_time)) {
                throw ValidationException::withMessages(['invoice' => __('pos.errors.month_locked')]);
            }

            $line->update(['visit_id' => $entry->id]);

            if ($service === null) {
                continue;
            }

            if ($service->category?->unit === 'day' && $line->quantity !== 1) {
                throw ValidationException::withMessages(['invoice' => __('pos.errors.daily_service_quantity')]);
            }

            if ($service->category?->unit === 'day' && in_array((int) $line->service_id, $existingServiceIds, true)) {
                throw ValidationException::withMessages([
                    'invoice' => __('pos.errors.already_on_entry', ['service' => $service->name]),
                ]);
            }

            // Query insert: the observer would only re-run sync(), and this
            // row is already billed on the POS invoice.
            EntryService::query()->insert([
                'entry_id' => $entry->id,
                'service_id' => $line->service_id,
                'invoice_item_id' => $line->id,
                'amount' => $line->quantity,
                'price_at_time' => $line->unit_price,
                'total' => $line->line_total,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $existingServiceIds[] = (int) $line->service_id;

            if (filled($service?->reference_column_name)) {
                $referenceColumns[$service->reference_column_name] = true;
            }
        }

        if ($referenceColumns !== []) {
            $entry->update($referenceColumns);
        }
    }

    public function removeCancelledSale(Invoice $invoice): void
    {
        $rows = EntryService::query()
            ->whereIn('invoice_item_id', $invoice->items()->select('id'))
            ->with(['entry', 'service'])
            ->get();

        foreach ($rows as $row) {
            $entry = StudentEntry::query()->lockForUpdate()->findOrFail($row->entry_id);
            if (LockedMonth::isLocked($entry->entry_time)) {
                throw ValidationException::withMessages(['invoice' => __('pos.errors.month_locked')]);
            }

            EntryService::query()->whereKey($row->id)->delete();
            $column = $row->service?->reference_column_name;

            if (filled($column)) {
                $entry->update([$column => $entry->services()
                    ->whereHas('service', fn ($query) => $query->where('reference_column_name', $column))
                    ->exists()]);
            }
        }
    }

    /**
     * Services this entry pays for on its own invoice: everything except
     * history rows and services already charged on a point-of-sale invoice.
     *
     * @return Collection<int, EntryService>
     */
    protected function ownServices(StudentEntry $entry): Collection
    {
        return $entry->services()
            ->where('pre_finance', false)
            ->with('invoiceItem.invoice')
            ->orderBy('id')
            ->get()
            ->reject(fn (EntryService $service) => $service->isBilledAtPointOfSale())
            ->values();
    }

    protected function createEntryInvoice(StudentEntry $entry): Invoice
    {
        $entry->loadMissing('student');

        // Entry money is collected on the spot: the invoice is born paid and
        // joins the open cash shift when there is one.
        return Invoice::create([
            'center_id' => $entry->center_id,
            'user_id' => Auth::id() ?? $entry->user_id,
            'number' => Invoice::nextNumberFor((int) $entry->center_id),
            'status' => InvoiceStatus::Paid,
            'beneficiary_name' => $entry->student?->getFilamentName() ?? '-',
            'student_id' => $entry->student_id,
            'entry_id' => $entry->id,
            'discount' => 0,
            'total' => 0,
            'note' => __('invoices.notes.entry_invoice'),
            'shift_id' => CashShift::openShiftForCenter((int) $entry->center_id)?->id,
        ]);
    }

    private function lockStudent(string $studentId): void
    {
        // Serializes daily services across multiple visits by the same student.
        Student::query()->lockForUpdate()->findOrFail($studentId);
    }

    /** Cancel without Invoice::markCancelled(), which also removes entry services. */
    protected function cancelQuietly(Invoice $invoice): void
    {
        $invoice->status = InvoiceStatus::Cancelled;
        $invoice->save();
    }
}
