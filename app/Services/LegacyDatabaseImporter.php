<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ExpenseType;
use App\Enums\InvoiceStatus;
use App\Enums\StockMovementType;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Imports one legacy DBRestaurantProV5 database (one center per file) into
 * the new financial tables, converting old-currency amounts (old = new x 100).
 *
 * Idempotent: every imported row stores its legacy primary key in legacy_key
 * ("item:42", "invoice:7", "expense:3", "opening:42"), and rows whose key
 * already exists for the center are skipped, so re-running never duplicates.
 *
 * The legacy connection is only ever read. Historical rows may fall in locked
 * months, so the locked-month guards are bypassed, like the Excel importer.
 */
class LegacyDatabaseImporter
{
    /** @var list<string> Non-fatal problems found while importing. */
    public array $warnings = [];

    /** @var array<int|string, int> legacy item id => new inventory_items.id */
    private array $itemMap = [];

    private ConnectionInterface $legacy;

    private int $centerId;

    private ?int $userId;

    private ?Carbon $cutoff;

    /**
     * @return array<string, array<string, int>> stats per phase
     */
    public function import(
        ConnectionInterface $legacy,
        int $centerId,
        ?int $userId,
        bool $dryRun,
        ?Carbon $cutoff,
    ): array {
        $this->legacy = $legacy;
        $this->centerId = $centerId;
        $this->userId = $userId;
        $this->cutoff = $cutoff;
        $this->warnings = [];
        $this->itemMap = [];

        $this->assertMappedSchemaExists();

        DB::beginTransaction();

        try {
            // InventoryItem carries no locked-month guard (catalog data).
            $stats = StockMovement::withoutLockedMonthGuard(
                fn () => Invoice::withoutLockedMonthGuard(
                    fn () => InvoiceItem::withoutLockedMonthGuard(
                        fn () => Expense::withoutLockedMonthGuard(
                            fn () => $this->run()
                        )
                    )
                )
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        // Dry run: accurate "would create" counts, then throw everything away.
        $dryRun ? DB::rollBack() : DB::commit();

        return $stats;
    }

    /** @return array<string, array<string, int>> */
    private function run(): array
    {
        return [
            'items' => $this->importItems(),
            'invoices' => $this->importInvoices(),
            'expenses' => $this->importExpenses(),
        ];
    }

    /**
     * Fails loudly when the configured map does not match the real legacy
     * schema — run finance:legacy-schema and fix config/legacy-finance.php.
     */
    private function assertMappedSchemaExists(): void
    {
        $schema = $this->legacy->getSchemaBuilder();

        foreach ((array) config('legacy-finance.map') as $section => $map) {
            $table = $map['table'];

            if (! $schema->hasTable($table)) {
                throw new InvalidArgumentException(
                    "جدول \"{$table}\" (قسم {$section}) غير موجود في قاعدة البيانات القديمة — "
                    .'شغّل finance:legacy-schema وصحّح config/legacy-finance.php.'
                );
            }

            $columns = array_filter(array_values(array_diff_key($map, ['table' => null])));

            foreach ($columns as $column) {
                if (! $schema->hasColumn($table, $column)) {
                    throw new InvalidArgumentException(
                        "عمود \"{$column}\" غير موجود في جدول \"{$table}\" — "
                        .'شغّل finance:legacy-schema وصحّح config/legacy-finance.php.'
                    );
                }
            }
        }
    }

    /**
     * Old currency = new x 100. With no cutoff every legacy amount converts;
     * with one, only rows dated strictly before it do.
     */
    private function convert(int|float|string|null $amount, ?Carbon $date): int
    {
        $amount = (float) ($amount ?? 0);

        if ($this->cutoff === null || $date === null || $date->lt($this->cutoff)) {
            $amount /= (int) config('legacy-finance.currency_divisor', 100);
        }

        return (int) round($amount);
    }

    private function toDate(mixed $value): ?Carbon
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string, int> */
    private function importItems(): array
    {
        $map = config('legacy-finance.map.items');
        $stats = ['legacy' => 0, 'created' => 0, 'skipped' => 0];

        foreach ($this->legacy->table($map['table'])->orderBy($map['id'])->cursor() as $row) {
            $row = (array) $row;
            $stats['legacy']++;

            $legacyId = $row[$map['id']];
            $key = "item:{$legacyId}";

            $existing = InventoryItem::query()
                ->where('center_id', $this->centerId)
                ->where('legacy_key', $key)
                ->first();

            if ($existing !== null) {
                $this->itemMap[$legacyId] = $existing->id;
                $stats['skipped']++;

                continue;
            }

            // Catalog prices carry no date: converted (or not) per the cutoff
            // like any other legacy amount, using "no date" = old currency.
            $item = InventoryItem::create([
                'center_id' => $this->centerId,
                'user_id' => $this->userId,
                'name' => trim((string) $row[$map['name']]),
                'category' => $map['category'] !== null ? ($row[$map['category']] ?? null) : null,
                'purchase_cost' => $this->convert($row[$map['purchase_cost']] ?? 0, null),
                'sale_price' => $this->convert($row[$map['sale_price']] ?? 0, null),
                'is_active' => true,
                'legacy_key' => $key,
            ]);

            $this->itemMap[$legacyId] = $item->id;
            $stats['created']++;

            $stock = (int) ($row[$map['stock']] ?? 0);

            if ($stock > 0) {
                StockMovement::create([
                    'center_id' => $this->centerId,
                    'user_id' => $this->userId,
                    'inventory_item_id' => $item->id,
                    'type' => StockMovementType::Adjustment,
                    'quantity' => $stock,
                    'cost' => $item->purchase_cost,
                    'note' => 'رصيد افتتاحي (استيراد النظام القديم)',
                    'legacy_key' => "opening:{$legacyId}",
                ]);
            }
        }

        return $stats;
    }

    /** @return array<string, int> */
    private function importInvoices(): array
    {
        $map = config('legacy-finance.map.invoices');
        $linesMap = config('legacy-finance.map.invoice_lines');
        $stats = ['legacy' => 0, 'created' => 0, 'skipped' => 0, 'legacy_total' => 0];

        $lines = collect($this->legacy->table($linesMap['table'])->get())
            ->map(fn ($line) => (array) $line)
            ->groupBy(fn (array $line) => (string) $line[$linesMap['bill_id']]);

        foreach ($this->legacy->table($map['table'])->orderBy($map['id'])->cursor() as $row) {
            $row = (array) $row;
            $stats['legacy']++;

            $legacyId = $row[$map['id']];
            $date = $this->toDate($row[$map['date']] ?? null);
            $total = $this->convert($row[$map['total']] ?? 0, $date);
            $stats['legacy_total'] += $total;

            $key = "invoice:{$legacyId}";

            $exists = Invoice::query()
                ->where('center_id', $this->centerId)
                ->where('legacy_key', $key)
                ->exists();

            if ($exists) {
                $stats['skipped']++;

                continue;
            }

            if ($date === null) {
                $this->warnings[] = "فاتورة قديمة {$legacyId}: بدون تاريخ صالح — استُخدم تاريخ اليوم.";
                $date = now();
            }

            // Historical invoices: Paid, no shift, no stock movements — the
            // stock snapshot already reflects these sales (same rule as the
            // Excel importer).
            $invoice = Invoice::create([
                'center_id' => $this->centerId,
                'user_id' => $this->userId,
                'number' => Invoice::nextNumberFor($this->centerId),
                'status' => InvoiceStatus::Paid,
                'beneficiary_name' => $this->beneficiaryName($row, $map),
                'discount' => $map['discount'] !== null ? $this->convert($row[$map['discount']] ?? 0, $date) : 0,
                'total' => $total,
                'note' => $this->invoiceNote($row, $map, $legacyId),
                'legacy_key' => $key,
                'created_at' => $date,
                'updated_at' => $date,
            ]);

            $this->createInvoiceLines($invoice, $lines->get((string) $legacyId, collect()), $linesMap, $date, $legacyId);

            $stats['created']++;
        }

        return $stats;
    }

    private function beneficiaryName(array $row, array $map): string
    {
        $name = $map['beneficiary'] !== null ? trim((string) ($row[$map['beneficiary']] ?? '')) : '';

        return $name !== '' ? $name : '-';
    }

    private function invoiceNote(array $row, array $map, int|string $legacyId): string
    {
        $note = $map['note'] !== null ? trim((string) ($row[$map['note']] ?? '')) : '';

        return trim("استيراد النظام القديم (فاتورة {$legacyId})".($note !== '' ? "\n{$note}" : ''));
    }

    /** @param  Collection<int, array<string, mixed>>  $lines */
    private function createInvoiceLines(Invoice $invoice, $lines, array $map, Carbon $date, int|string $legacyBillId): void
    {
        foreach ($lines as $line) {
            $itemId = $this->itemMap[$line[$map['item_id']]] ?? null;

            if ($itemId === null) {
                $this->warnings[] = "فاتورة قديمة {$legacyBillId}: سطر يشير لمادة غير موجودة "
                    ."({$line[$map['item_id']]}) — أُهمل السطر وبقي الإجمالي كما هو.";

                continue;
            }

            $quantity = max(1, (int) ($line[$map['quantity']] ?? 1));
            $unitPrice = $this->convert($line[$map['unit_price']] ?? 0, $date);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'inventory_item_id' => $itemId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $quantity * $unitPrice,
            ]);
        }
    }

    /** @return array<string, int> */
    private function importExpenses(): array
    {
        $map = config('legacy-finance.map.expenses');
        $types = (array) config('legacy-finance.expense_types');
        $stats = ['legacy' => 0, 'created' => 0, 'skipped' => 0, 'legacy_total' => 0];

        foreach ($this->legacy->table($map['table'])->orderBy($map['id'])->cursor() as $row) {
            $row = (array) $row;
            $stats['legacy']++;

            $legacyId = $row[$map['id']];
            $date = $this->toDate($row[$map['date']] ?? null);
            $amount = $this->convert($row[$map['amount']] ?? 0, $date);
            $stats['legacy_total'] += $amount;

            $key = "expense:{$legacyId}";

            $exists = Expense::query()
                ->where('center_id', $this->centerId)
                ->where('legacy_key', $key)
                ->exists();

            if ($exists) {
                $stats['skipped']++;

                continue;
            }

            if ($date === null) {
                $this->warnings[] = "مصروف قديم {$legacyId}: بدون تاريخ صالح — استُخدم تاريخ اليوم.";
                $date = now();
            }

            $label = trim((string) ($row[$map['type']] ?? ''));
            $type = $types[$label] ?? ExpenseType::Other;

            $note = trim((string) ($map['note'] !== null ? ($row[$map['note']] ?? '') : ''));

            if ($type === ExpenseType::Other && $label !== '' && ! isset($types[$label])) {
                $note = trim("النوع الأصلي: {$label}".($note !== '' ? "\n{$note}" : ''));
            }

            Expense::create([
                'center_id' => $this->centerId,
                'user_id' => $this->userId,
                'type' => $type,
                'payee' => $map['payee'] !== null ? ($row[$map['payee']] ?? null) : null,
                'amount' => $amount,
                'date' => $date->toDateString(),
                'note' => $note !== '' ? $note : null,
                'legacy_key' => $key,
                'created_at' => $date,
                'updated_at' => $date,
            ]);

            $stats['created']++;
        }

        return $stats;
    }

    /**
     * Legacy-vs-new reconciliation for one center: counts and totals on both
     * sides, computed AFTER the import so the two columns must match.
     *
     * @return array<string, array{legacy: int|float, imported: int|float}>
     */
    public function reconcile(ConnectionInterface $legacy, int $centerId, ?Carbon $cutoff): array
    {
        $this->legacy = $legacy;
        $this->centerId = $centerId;
        $this->cutoff = $cutoff;

        $map = config('legacy-finance.map');

        $legacyInvoices = collect($legacy->table($map['invoices']['table'])
            ->get([$map['invoices']['date'].' as d', $map['invoices']['total'].' as t']));
        $legacyExpenses = collect($legacy->table($map['expenses']['table'])
            ->get([$map['expenses']['date'].' as d', $map['expenses']['amount'].' as t']));

        $convertSum = fn ($rows) => $rows->sum(
            fn ($row) => $this->convert($row->t, $this->toDate($row->d))
        );

        $new = fn (string $model) => $model::query()
            ->where('center_id', $centerId)
            ->whereNotNull('legacy_key');

        return [
            'items_count' => [
                'legacy' => $legacy->table($map['items']['table'])->count(),
                'imported' => $new(InventoryItem::class)->count(),
            ],
            'stock_units' => [
                'legacy' => (int) $legacy->table($map['items']['table'])->sum($map['items']['stock']),
                'imported' => (int) $new(StockMovement::class)
                    ->where('legacy_key', 'like', 'opening:%')->sum('quantity'),
            ],
            'invoices_count' => [
                'legacy' => $legacyInvoices->count(),
                'imported' => $new(Invoice::class)->count(),
            ],
            'invoices_total' => [
                'legacy' => $convertSum($legacyInvoices),
                'imported' => (int) $new(Invoice::class)->sum('total'),
            ],
            'expenses_count' => [
                'legacy' => $legacyExpenses->count(),
                'imported' => $new(Expense::class)->count(),
            ],
            'expenses_total' => [
                'legacy' => $convertSum($legacyExpenses),
                'imported' => (int) $new(Expense::class)->sum('amount'),
            ],
        ];
    }
}
