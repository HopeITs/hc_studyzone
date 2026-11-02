<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ExpenseType;
use App\Enums\InventoryUnit;
use App\Enums\InvoiceStatus;
use App\Enums\StockMovementType;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Imports the filled Arabic Excel templates (financial_templates/*.xlsx)
 * into inventory items, expenses and historical invoices.
 *
 * Template layout (all three files): row 1 legend, row 2 headers,
 * row 3 yellow example row (skipped by its "مثال فقط" marker), data from row 3+.
 */
class FinanceTemplateImporter
{
    private const EXAMPLE_MARKER = 'مثال فقط';

    /** Arabic dropdown label → unit enum value. */
    private const UNITS = [
        'قطعة' => InventoryUnit::Piece,
        'علبة' => InventoryUnit::Box,
        'كغ' => InventoryUnit::Kg,
        'رزمة' => InventoryUnit::Ream,
        'متر' => InventoryUnit::Meter,
    ];

    /** Arabic dropdown label → expense type enum value. */
    private const EXPENSE_TYPES = [
        'تشغيلية' => ExpenseType::Operations,
        'صيانة' => ExpenseType::Maintenance,
        'شراء مستودع' => ExpenseType::WarehousePurchase,
        'نقل' => ExpenseType::Transport,
        'ضيافة' => ExpenseType::Hospitality,
        'سلفة' => ExpenseType::Advance,
        'أخرى' => ExpenseType::Other,
    ];

    /** Arabic dropdown label → invoice status enum value. */
    private const INVOICE_STATUSES = [
        'مدفوعة' => InvoiceStatus::Paid,
        'غير مدفوعة' => InvoiceStatus::Unpaid,
        'ملغاة' => InvoiceStatus::Cancelled,
    ];

    /** @var list<string> Human-readable problems collected while parsing. */
    public array $errors = [];

    /**
     * @return array{type: string, created: int, skipped: int}
     */
    public function import(string $path, int $centerId, ?int $userId, bool $dryRun = false): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $headers = $this->rowValues($sheet, 2);
        $type = $this->detectTemplate($headers);
        $rows = $this->dataRows($sheet, count($headers));

        $this->errors = [];

        $parsed = match ($type) {
            'inventory' => $this->parseInventory($rows),
            'expenses' => $this->parseExpenses($rows),
            'invoices' => $this->parseInvoices($rows),
        };

        if ($this->errors !== []) {
            return ['type' => $type, 'created' => 0, 'skipped' => count($rows)];
        }

        if (! $dryRun) {
            DB::transaction(fn () => match ($type) {
                'inventory' => $this->saveInventory($parsed, $centerId, $userId),
                'expenses' => $this->saveExpenses($parsed, $centerId, $userId),
                'invoices' => $this->saveInvoices($parsed, $centerId, $userId),
            });
        }

        return ['type' => $type, 'created' => count($parsed), 'skipped' => 0];
    }

    private function detectTemplate(array $headers): string
    {
        $first = $headers[0] ?? '';

        return match (true) {
            $first === 'اسم المادة' => 'inventory',
            in_array('نوع المصروف', $headers, true) => 'expenses',
            in_array('اسم المستفيد', $headers, true) => 'invoices',
            default => throw new InvalidArgumentException(
                'لم أتعرف على نوع الملف — تأكد أنه أحد النماذج الثلاثة بدون تغيير العناوين.'
            ),
        };
    }

    /** @return list<array{row: int, cells: list<mixed>}> */
    private function dataRows(Worksheet $sheet, int $columns): array
    {
        $rows = [];

        foreach (range(3, $sheet->getHighestDataRow()) as $rowIndex) {
            $cells = [];
            $isExample = false;
            $isEmpty = true;

            foreach (range(1, $columns) as $col) {
                $value = $sheet->getCell([$col, $rowIndex])->getValue();

                // Files written by some tools store strings as rich text objects.
                if ($value instanceof RichText) {
                    $value = $value->getPlainText();
                }

                if (is_string($value)) {
                    $value = trim($value);

                    if (str_contains($value, self::EXAMPLE_MARKER)) {
                        $isExample = true;
                    }
                }

                if ($value !== null && $value !== '') {
                    $isEmpty = false;
                }

                $cells[] = $value === '' ? null : $value;
            }

            if (! $isEmpty && ! $isExample) {
                $rows[] = ['row' => $rowIndex, 'cells' => $cells];
            }
        }

        return $rows;
    }

    /** @return list<string> */
    private function rowValues(Worksheet $sheet, int $rowIndex): array
    {
        $values = [];

        foreach (range(1, (int) Coordinate::columnIndexFromString($sheet->getHighestDataColumn())) as $col) {
            $value = $sheet->getCell([$col, $rowIndex])->getValue();

            if ($value instanceof RichText) {
                $value = $value->getPlainText();
            }

            if ($value !== null && trim((string) $value) !== '') {
                $values[] = trim((string) $value);
            }
        }

        return $values;
    }

    private function parseInventory(array $rows): array
    {
        return array_values(array_filter(array_map(function (array $row): ?array {
            [$name, $category, $unit, $purchaseCost, $salePrice, $quantity, $threshold, $supplier, $lastPurchase, $note] = array_pad($row['cells'], 10, null);

            if (! is_string($name) || $name === '') {
                $this->errors[] = "سطر {$row['row']}: اسم المادة فارغ.";

                return null;
            }

            $unitEnum = self::UNITS[trim((string) $unit)] ?? null;

            if ($unit !== null && $unitEnum === null) {
                $this->errors[] = "سطر {$row['row']}: الوحدة \"{$unit}\" غير معروفة.";

                return null;
            }

            return [
                'name' => $name,
                'category' => $category !== null ? (string) $category : null,
                'unit' => $unitEnum ?? InventoryUnit::default(),
                'purchase_cost' => $this->toInt($purchaseCost, $row['row'], 'سعر الشراء'),
                'sale_price' => $this->toInt($salePrice, $row['row'], 'سعر البيع'),
                'opening_stock' => $this->toInt($quantity, $row['row'], 'الكمية الحالية'),
                'min_stock_threshold' => $this->toInt($threshold, $row['row'], 'حد التنبيه'),
                'supplier' => $supplier !== null ? (string) $supplier : null,
                'last_purchase_date' => $this->toDate($lastPurchase, $row['row'], 'تاريخ آخر شراء', required: false),
            ];
        }, $rows)));
    }

    private function saveInventory(array $items, int $centerId, ?int $userId): void
    {
        foreach ($items as $item) {
            $openingStock = $item['opening_stock'];
            unset($item['opening_stock']);

            $model = InventoryItem::create($item + [
                'center_id' => $centerId,
                'user_id' => $userId,
                'is_active' => true,
            ]);

            if ($openingStock > 0) {
                StockMovement::create([
                    'center_id' => $centerId,
                    'user_id' => $userId,
                    'inventory_item_id' => $model->id,
                    'type' => StockMovementType::Adjustment,
                    'quantity' => $openingStock,
                    'cost' => $item['purchase_cost'],
                    'note' => 'رصيد افتتاحي (استيراد اكسل)',
                ]);
            }
        }
    }

    private function parseExpenses(array $rows): array
    {
        return array_values(array_filter(array_map(function (array $row): ?array {
            [$date, $type, $payee, $amount, $note] = array_pad($row['cells'], 5, null);

            $typeEnum = self::EXPENSE_TYPES[trim((string) $type)] ?? null;

            if ($typeEnum === null) {
                $this->errors[] = "سطر {$row['row']}: نوع المصروف \"{$type}\" غير معروف.";

                return null;
            }

            return [
                'date' => $this->toDate($date, $row['row'], 'التاريخ', required: true),
                'type' => $typeEnum,
                'payee' => $payee !== null ? (string) $payee : null,
                'amount' => $this->toInt($amount, $row['row'], 'المبلغ'),
                'note' => $note !== null ? (string) $note : null,
            ];
        }, $rows)));
    }

    private function saveExpenses(array $expenses, int $centerId, ?int $userId): void
    {
        foreach ($expenses as $expense) {
            Expense::create($expense + ['center_id' => $centerId, 'user_id' => $userId]);
        }
    }

    private function parseInvoices(array $rows): array
    {
        return array_values(array_filter(array_map(function (array $row): ?array {
            [$date, $beneficiary, $studentNumber, $description, $total, $discount, $status, $note] = array_pad($row['cells'], 8, null);

            if (! is_string($beneficiary) || $beneficiary === '') {
                $this->errors[] = "سطر {$row['row']}: اسم المستفيد فارغ.";

                return null;
            }

            $statusEnum = self::INVOICE_STATUSES[trim((string) $status)] ?? null;

            if ($statusEnum === null) {
                $this->errors[] = "سطر {$row['row']}: الحالة \"{$status}\" غير معروفة.";

                return null;
            }

            $fullNote = trim(($description !== null ? 'البيان: '.$description : '').($note !== null ? "\n".$note : ''));

            return [
                'created_at' => $this->toDate($date, $row['row'], 'التاريخ', required: true),
                'beneficiary_name' => $beneficiary,
                'student_number' => $studentNumber !== null ? (string) $studentNumber : null,
                'total' => $this->toInt($total, $row['row'], 'الإجمالي'),
                'discount' => $this->toInt($discount, $row['row'], 'الحسم'),
                'status' => $statusEnum,
                'note' => $fullNote !== '' ? $fullNote : null,
            ];
        }, $rows)));
    }

    private function saveInvoices(array $invoices, int $centerId, ?int $userId): void
    {
        foreach ($invoices as $invoice) {
            // Historical invoices: number assigned sequentially, no stock movements,
            // no shift — they predate the system, so markPaid() is intentionally skipped.
            Invoice::create($invoice + [
                'center_id' => $centerId,
                'user_id' => $userId,
                'number' => Invoice::nextNumberFor($centerId),
            ]);
        }
    }

    private function toInt(mixed $value, int $row, string $field): int
    {
        if ($value === null) {
            return 0;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $this->errors[] = "سطر {$row}: {$field} يجب أن يكون رقماً (وجدت \"{$value}\").";

        return 0;
    }

    private function toDate(mixed $value, int $row, string $field, bool $required): ?Carbon
    {
        if ($value === null) {
            if ($required) {
                $this->errors[] = "سطر {$row}: {$field} فارغ.";
            }

            return null;
        }

        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value));
        }

        try {
            return Carbon::parse(trim((string) $value));
        } catch (\Throwable) {
            $this->errors[] = "سطر {$row}: {$field} \"{$value}\" ليس تاريخاً صحيحاً (الصيغة: 2026-10-01).";

            return null;
        }
    }
}
