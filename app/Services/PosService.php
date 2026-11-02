<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Roles;
use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\LockedMonth;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\StockMovement;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosService
{
    /**
     * Create a POS invoice with server-side price snapshots, optionally
     * collecting it immediately. Everything runs in one transaction, so a
     * refused collection (e.g. insufficient stock) leaves no draft behind.
     *
     * @param  array<int, array{inventory_item_id?: int|null, service_id?: int|null, quantity: int}>  $lines
     *
     * @throws ValidationException on any business refusal, with a translated message.
     */
    public function createInvoice(
        int $centerId,
        array $lines,
        string $beneficiaryName,
        ?string $studentNumber,
        int $discount,
        ?string $note,
        bool $collect,
        ?int $userId = null,
        ?string $studentId = null,
    ): Invoice {
        $this->assertUserMayActForCenter($userId, $centerId);

        if (LockedMonth::isLocked(now())) {
            throw ValidationException::withMessages([
                'pos' => __('pos.errors.month_locked'),
            ]);
        }

        $student = null;

        if ($studentId !== null) {
            $student = Student::query()->find($studentId);

            if ($student === null) {
                throw ValidationException::withMessages([
                    'pos' => __('pos.errors.student_not_found'),
                ]);
            }

            // Snapshot the registered name server-side — never trust the browser.
            $beneficiaryName = $student->getFilamentName();
        }

        if (trim($beneficiaryName) === '') {
            throw ValidationException::withMessages([
                'pos' => __('pos.errors.beneficiary_required'),
            ]);
        }

        $lines = $this->normalizeLines($lines);
        $priced = $this->priceLines($centerId, $lines);

        return DB::transaction(function () use (
            $centerId,
            $priced,
            $beneficiaryName,
            $studentNumber,
            $discount,
            $note,
            $collect,
            $userId,
            $student,
        ): Invoice {
            $subtotal = 0;

            foreach ($priced as $line) {
                $subtotal += $line['quantity'] * $line['unit_price'];
            }

            $discount = max(0, min($discount, $subtotal));

            $invoice = Invoice::create([
                'center_id' => $centerId,
                'user_id' => $userId,
                'number' => Invoice::nextNumberFor($centerId),
                'beneficiary_name' => $beneficiaryName,
                'student_number' => $studentNumber !== null && trim($studentNumber) !== '' ? $studentNumber : null,
                'student_id' => $student?->id,
                'discount' => $discount,
                'total' => max(0, $subtotal - $discount),
                'note' => $note,
            ]);

            foreach ($priced as $line) {
                $invoice->items()->create([
                    'inventory_item_id' => $line['inventory_item_id'],
                    'service_id' => $line['service_id'],
                    'quantity' => $line['quantity'],
                    // Snapshot: later catalog price changes must not affect this line.
                    'unit_price' => $line['unit_price'],
                    'line_total' => $line['quantity'] * $line['unit_price'],
                ]);
            }

            if ($collect) {
                // Throws on insufficient stock or a locked month and rolls
                // the whole invoice back with it.
                $invoice->markPaid();
            }

            return $invoice;
        });
    }

    /**
     * Turn a cashier discount input into a clamped absolute amount.
     * $type is 'percent' or 'amount'.
     */
    public function resolveDiscount(int $subtotal, string $type, int|float $value): int
    {
        $amount = $type === 'percent'
            ? (int) round($subtotal * ((float) $value) / 100)
            : (int) $value;

        return max(0, min($amount, $subtotal));
    }

    /**
     * Hand stock to the team: deducting team_allocation movements with no
     * invoice and no revenue. Availability is checked after locking the
     * item rows, so two allocations cannot both consume the last units.
     *
     * @param  array<int, array{inventory_item_id: int, quantity: int}>  $lines
     *
     * @throws ValidationException on any business refusal, with a translated message.
     */
    public function recordTeamAllocation(
        int $centerId,
        array $lines,
        string $recipient,
        ?string $note,
        ?int $userId = null,
    ): void {
        $this->assertUserMayActForCenter($userId, $centerId);

        if (LockedMonth::isLocked(now())) {
            throw ValidationException::withMessages([
                'pos' => __('pos.errors.month_locked'),
            ]);
        }

        if (trim($recipient) === '') {
            throw ValidationException::withMessages([
                'pos' => __('pos.errors.recipient_required'),
            ]);
        }

        // Aggregate quantities per item so availability is checked once per item.
        $needed = [];

        foreach ($lines as $line) {
            $itemId = (int) ($line['inventory_item_id'] ?? 0);
            $quantity = (int) ($line['quantity'] ?? 0);

            if ($itemId <= 0) {
                throw ValidationException::withMessages([
                    'pos' => __('pos.errors.invalid_line'),
                ]);
            }

            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    'pos' => __('pos.errors.invalid_quantity'),
                ]);
            }

            $needed[$itemId] = ($needed[$itemId] ?? 0) + $quantity;
        }

        if ($needed === []) {
            throw ValidationException::withMessages([
                'pos' => __('pos.errors.empty_lines'),
            ]);
        }

        DB::transaction(function () use ($centerId, $needed, $recipient, $note, $userId): void {
            $items = InventoryItem::query()
                ->where('center_id', $centerId)
                ->where('is_active', true)
                ->whereIn('id', array_keys($needed))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($needed as $itemId => $quantity) {
                $item = $items->get($itemId);

                if ($item === null) {
                    throw ValidationException::withMessages([
                        'pos' => __('pos.errors.item_unavailable'),
                    ]);
                }

                $available = (int) $item->current_stock;

                if ($quantity > $available) {
                    throw ValidationException::withMessages([
                        'pos' => __('pos.errors.insufficient_stock', [
                            'item' => $item->name,
                            'available' => $available,
                        ]),
                    ]);
                }
            }

            $movementNote = __('pos.notes.team_allocation', ['recipient' => $recipient]);

            if ($note !== null && trim($note) !== '') {
                $movementNote .= ' — '.$note;
            }

            foreach ($needed as $itemId => $quantity) {
                StockMovement::create([
                    'center_id' => $centerId,
                    'user_id' => $userId,
                    'inventory_item_id' => $itemId,
                    'type' => StockMovementType::TeamAllocation,
                    'quantity' => -$quantity,
                    'note' => $movementNote,
                ]);
            }
        });
    }

    /**
     * Defence in depth: a center-bound acting user (CenterAdmin, Cashier) may
     * only ever write to their own center, whatever center id the caller
     * resolved. The UI already forces this; this guard catches tampering.
     *
     * @throws ValidationException
     */
    protected function assertUserMayActForCenter(?int $userId, int $centerId): void
    {
        if ($userId === null) {
            return;
        }

        $user = User::query()->find($userId);

        if ($user === null) {
            return;
        }

        $centerBound = in_array($user->role, [Roles::CenterAdmin(), Roles::Cashier()], true);

        if ($centerBound && $user->center_id !== null && (int) $user->center_id !== $centerId) {
            throw ValidationException::withMessages([
                'pos' => __('pos.errors.center_mismatch'),
            ]);
        }
    }

    /**
     * Validate line shape: positive quantity and exactly one target
     * (inventory item or service) per line.
     *
     * @return array<int, array{inventory_item_id: ?int, service_id: ?int, quantity: int}>
     */
    protected function normalizeLines(array $lines): array
    {
        $normalized = [];

        foreach ($lines as $line) {
            $itemId = isset($line['inventory_item_id']) && (int) $line['inventory_item_id'] > 0
                ? (int) $line['inventory_item_id']
                : null;
            $serviceId = isset($line['service_id']) && (int) $line['service_id'] > 0
                ? (int) $line['service_id']
                : null;
            $quantity = (int) ($line['quantity'] ?? 0);

            if (($itemId === null) === ($serviceId === null)) {
                throw ValidationException::withMessages([
                    'pos' => __('pos.errors.invalid_line'),
                ]);
            }

            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    'pos' => __('pos.errors.invalid_quantity'),
                ]);
            }

            $normalized[] = [
                'inventory_item_id' => $itemId,
                'service_id' => $serviceId,
                'quantity' => $quantity,
            ];
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'pos' => __('pos.errors.empty_lines'),
            ]);
        }

        return $normalized;
    }

    /**
     * Resolve the unit price of every line on the server — never trust the
     * browser. Inventory items price from their sale_price; services from
     * the center's active ServicePrice.
     *
     * @param  array<int, array{inventory_item_id: ?int, service_id: ?int, quantity: int}>  $lines
     * @return array<int, array{inventory_item_id: ?int, service_id: ?int, quantity: int, unit_price: int}>
     */
    protected function priceLines(int $centerId, array $lines): array
    {
        $itemIds = array_values(array_filter(array_column($lines, 'inventory_item_id')));
        $serviceIds = array_values(array_filter(array_column($lines, 'service_id')));

        $items = InventoryItem::query()
            ->where('center_id', $centerId)
            ->where('is_active', true)
            ->whereIn('id', $itemIds)
            ->get()
            ->keyBy('id');

        $services = Service::query()
            ->where('is_active', true)
            ->whereIn('id', $serviceIds)
            ->get()
            ->keyBy('id');

        $servicePrices = ServicePrice::activePricesFor($centerId, $serviceIds);

        $priced = [];

        foreach ($lines as $line) {
            if ($line['inventory_item_id'] !== null) {
                $item = $items->get($line['inventory_item_id']);

                if ($item === null) {
                    throw ValidationException::withMessages([
                        'pos' => __('pos.errors.item_unavailable'),
                    ]);
                }

                $line['unit_price'] = (int) $item->sale_price;
            } else {
                $service = $services->get($line['service_id']);

                if ($service === null) {
                    throw ValidationException::withMessages([
                        'pos' => __('pos.errors.item_unavailable'),
                    ]);
                }

                if (! array_key_exists($service->id, $servicePrices)) {
                    throw ValidationException::withMessages([
                        'pos' => __('pos.errors.no_price', ['service' => $service->name]),
                    ]);
                }

                $line['unit_price'] = $servicePrices[$service->id];
            }

            $priced[] = $line;
        }

        return $priced;
    }
}
