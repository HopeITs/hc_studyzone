<?php

namespace App\Observers;

use App\Models\EntryService;
use App\Models\LockedMonth;
use App\Models\StudentEntry;
use App\Services\EntryInvoiceService;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

class EntryServiceObserver
{
    /**
     * Adding/editing a service on an entry changes that entry's total, so it
     * must be blocked the same way editing the entry itself is once the
     * entry's month has been locked by the General Admin.
     */
    public function saving(EntryService $entryService): void
    {
        $this->guard($entryService);

        if (! $entryService->exists || $entryService->isDirty(['service_id', 'amount'])) {
            app(EntryInvoiceService::class)->assertDailyServiceAvailable($entryService);
        }

        if ($entryService->exists && $entryService->isDirty() && $entryService->isBilledAtPointOfSale()) {
            $this->refusePointOfSaleRow();
        }
    }

    public function deleting(EntryService $entryService): void
    {
        $this->guard($entryService);

        if ($entryService->isBilledAtPointOfSale()) {
            $this->refusePointOfSaleRow();
        }
    }

    /** Keep the entry invoice in step with the entry's services. */
    public function saved(EntryService $entryService): void
    {
        app(EntryInvoiceService::class)->sync($entryService->entry_id);
    }

    public function deleted(EntryService $entryService): void
    {
        app(EntryInvoiceService::class)->sync($entryService->entry_id);
    }

    protected function guard(EntryService $entryService): void
    {
        $entry = $entryService->relationLoaded('entry')
            ? $entryService->entry
            : StudentEntry::find($entryService->entry_id);

        if (! $entry || ! LockedMonth::isLocked($entry->entry_time)) {
            return;
        }

        Notification::make()
            ->title(__('general.messages.month_locked_title'))
            ->body(__('general.messages.month_locked_body'))
            ->danger()
            ->send();

        throw new Halt;
    }

    /** A service paid at the point of sale is changed there (cancel the invoice), not on the entry. */
    protected function refusePointOfSaleRow(): never
    {
        Notification::make()
            ->title(__('pos.errors.entry_service_from_pos'))
            ->danger()
            ->send();

        throw new Halt;
    }
}
