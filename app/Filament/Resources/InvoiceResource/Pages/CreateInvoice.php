<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\Roles;
use App\Filament\Resources\InvoiceResource;
use App\Models\Invoice;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user = Auth::user();

        // A CenterAdmin can only ever invoice their own center,
        // no matter what the client submitted.
        $centerId = $user->role === Roles::CenterAdmin()
            ? (int) $user->center_id
            : (int) ($data['center_id'] ?? 0);

        try {
            return DB::transaction(function () use ($data, $centerId): Invoice {
                // Recompute every line server-side from snapshot prices —
                // quantities come from the form, prices never do.
                $lines = InvoiceResource::buildLines($centerId, $data['items'] ?? []);

                $discount = max(0, (int) ($data['discount'] ?? 0));
                $total = max(0, (int) array_sum(array_column($lines, 'line_total')) - $discount);

                $invoice = Invoice::create([
                    'center_id' => $centerId,
                    'user_id' => Auth::id(),
                    'number' => Invoice::nextNumberFor($centerId),
                    'status' => InvoiceStatus::Unpaid,
                    'beneficiary_name' => $data['beneficiary_name'],
                    'student_number' => $data['student_number'] ?? null,
                    'payment_method' => PaymentMethod::Cash,
                    'discount' => $discount,
                    'total' => $total,
                    'note' => $data['note'] ?? null,
                ]);

                $invoice->items()->createMany($lines);

                return $invoice;
            });
        } catch (ValidationException $e) {
            Notification::make()
                ->danger()
                ->title(__('invoices.messages.create_failed'))
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
