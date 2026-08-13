<?php

namespace App\Observers;

use App\Models\EntryService;
use App\Models\LockedMonth;
use App\Models\StudentEntry;
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
    }

    public function deleting(EntryService $entryService): void
    {
        $this->guard($entryService);
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
}
