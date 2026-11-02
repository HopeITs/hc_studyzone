<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\LockedMonth;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    /** Feed the existing lines into the non-relationship 'items' repeater. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Invoice $invoice */
        $invoice = $this->getRecord();

        $data['items'] = $invoice->items->map(fn ($item): array => [
            'kind' => $item->inventory_item_id ? 'inventory' : 'service',
            'inventory_item_id' => $item->inventory_item_id,
            'service_id' => $item->service_id,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
        ])->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Invoice $record */
        try {
            if ($record->status !== InvoiceStatus::Unpaid) {
                throw ValidationException::withMessages([
                    'invoice' => __('invoices.errors.not_unpaid'),
                ]);
            }

            if (LockedMonth::isLocked($record->created_at)) {
                throw ValidationException::withMessages([
                    'invoice' => __('invoices.errors.month_locked'),
                ]);
            }

            return DB::transaction(function () use ($record, $data): Invoice {
                // The center never changes on edit; lines are rebuilt from
                // scratch with fresh server-side snapshot prices.
                $lines = InvoiceResource::buildLines((int) $record->center_id, $data['items'] ?? []);

                $discount = max(0, (int) ($data['discount'] ?? 0));
                $total = max(0, (int) array_sum(array_column($lines, 'line_total')) - $discount);

                $record->items()->delete();
                $record->items()->createMany($lines);

                $record->update([
                    'beneficiary_name' => $data['beneficiary_name'],
                    'student_number' => $data['student_number'] ?? null,
                    'discount' => $discount,
                    'total' => $total,
                    'note' => $data['note'] ?? null,
                ]);

                return $record;
            });
        } catch (ValidationException $e) {
            Notification::make()
                ->danger()
                ->title(__('invoices.messages.update_failed'))
                ->body(collect($e->errors())->flatten()->first())
                ->send();

            throw $e;
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
