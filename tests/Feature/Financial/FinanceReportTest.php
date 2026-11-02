<?php

use App\Enums\ExpenseType;
use App\Enums\InvoiceStatus;
use App\Filament\Pages\PointOfSale;
use App\Models\Center;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\StockMovement;
use App\Services\FinanceReportService;
use App\Support\FinanceDisplay;
use Illuminate\Support\Str;

function frInvoice(Center $center, InvoiceStatus $status = InvoiceStatus::Paid, array $attrs = []): Invoice
{
    return Invoice::factory()->create(array_merge([
        'center_id' => $center->id,
        'status' => $status,
    ], $attrs));
}

function frItemLine(Invoice $invoice, ?string $category, int $lineTotal): InvoiceItem
{
    $item = InventoryItem::factory()->create([
        'center_id' => $invoice->center_id,
        'category' => $category,
    ]);

    return InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'inventory_item_id' => $item->id,
        'service_id' => null,
        'quantity' => 1,
        'unit_price' => $lineTotal,
        'line_total' => $lineTotal,
    ]);
}

function frServiceLine(Invoice $invoice, string $categoryName, int $lineTotal): InvoiceItem
{
    $category = ServiceCategory::firstOrCreate(['name' => $categoryName], ['unit' => 'جلسة']);

    $service = Service::create([
        'name' => 'خدمة '.Str::random(6),
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    return InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'inventory_item_id' => null,
        'service_id' => $service->id,
        'quantity' => 1,
        'unit_price' => $lineTotal,
        'line_total' => $lineTotal,
    ]);
}

function frExpense(Center $center, ExpenseType $type, int $amount, string $date): Expense
{
    return Expense::factory()->create([
        'center_id' => $center->id,
        'type' => $type,
        'amount' => $amount,
        'date' => $date,
    ]);
}

function frReports(): FinanceReportService
{
    return app(FinanceReportService::class);
}

it('mirrors the POS category tabs', function () {
    expect(FinanceReportService::SALES_CATEGORIES)->toBe(PointOfSale::CATEGORY_TABS);
});

/*
|--------------------------------------------------------------------------
| unifiedTotals
|--------------------------------------------------------------------------
*/

it('sums income net of discount and all expenses with no filters', function () {
    $center = testCenter();

    // Paid: lines 300 + 200, discount 50 → total 450.
    $paidA = frInvoice($center, attrs: ['discount' => 50, 'total' => 450]);
    frItemLine($paidA, 'قرطاسية', 300);
    frItemLine($paidA, 'مشروبات', 200);

    // Paid: one line, no discount → total 100.
    $paidB = frInvoice($center, attrs: ['total' => 100]);
    frItemLine($paidB, 'مشروبات', 100);

    // Never income: unpaid and cancelled.
    frItemLine(frInvoice($center, InvoiceStatus::Unpaid, ['total' => 999]), 'قرطاسية', 999);
    frItemLine(frInvoice($center, InvoiceStatus::Cancelled, ['total' => 888]), 'قرطاسية', 888);

    frExpense($center, ExpenseType::Operations, 120, today()->toDateString());
    frExpense($center, ExpenseType::Transport, 80, today()->toDateString());

    $totals = frReports()->unifiedTotals([]);

    expect($totals)->toBe(['income' => 550, 'expenses' => 200, 'net' => 350]);

    // movement=all is the same as no movement filter.
    expect(frReports()->unifiedTotals(['movement' => 'all']))->toBe($totals);
});

it('restricts unified totals to the date range', function () {
    $center = testCenter();

    frItemLine(frInvoice($center, attrs: ['total' => 100, 'created_at' => '2026-09-01 10:00:00']), null, 100);
    frItemLine(frInvoice($center, attrs: ['total' => 250, 'created_at' => '2026-10-10 10:00:00']), null, 250);
    frItemLine(frInvoice($center, attrs: ['total' => 400, 'created_at' => '2026-11-20 10:00:00']), null, 400);

    frExpense($center, ExpenseType::Operations, 30, '2026-09-01');
    frExpense($center, ExpenseType::Operations, 70, '2026-10-15');
    frExpense($center, ExpenseType::Operations, 500, '2026-11-20');

    $totals = frReports()->unifiedTotals(['from' => '2026-10-01', 'to' => '2026-10-31']);

    expect($totals)->toBe(['income' => 250, 'expenses' => 70, 'net' => 180]);

    // Open-ended bounds work too.
    expect(frReports()->unifiedTotals(['from' => '2026-10-01'])['income'])->toBe(650)
        ->and(frReports()->unifiedTotals(['to' => '2026-10-31'])['expenses'])->toBe(100);
});

it('filters unified totals by center and treats empty center_ids as all', function () {
    $centerA = testCenter();
    $centerB = testCenter();

    frItemLine(frInvoice($centerA, attrs: ['total' => 300]), null, 300);
    frItemLine(frInvoice($centerB, attrs: ['total' => 500]), null, 500);

    frExpense($centerA, ExpenseType::Maintenance, 40, today()->toDateString());
    frExpense($centerB, ExpenseType::Maintenance, 60, today()->toDateString());

    expect(frReports()->unifiedTotals(['center_ids' => [$centerA->id]]))
        ->toBe(['income' => 300, 'expenses' => 40, 'net' => 260]);

    expect(frReports()->unifiedTotals(['center_ids' => []]))
        ->toBe(['income' => 800, 'expenses' => 100, 'net' => 700]);
});

it('honours the movement filter', function () {
    $center = testCenter();

    frItemLine(frInvoice($center, attrs: ['total' => 300]), null, 300);
    frExpense($center, ExpenseType::Operations, 120, today()->toDateString());

    expect(frReports()->unifiedTotals(['movement' => 'income']))
        ->toBe(['income' => 300, 'expenses' => 0, 'net' => 300]);

    expect(frReports()->unifiedTotals(['movement' => 'expenses']))
        ->toBe(['income' => 0, 'expenses' => 120, 'net' => -120]);
});

it('reports gross line totals for a sales-category filter and excludes expenses', function () {
    $center = testCenter();

    // Mixed invoice: only the قرطاسية line counts, gross of the discount.
    $mixed = frInvoice($center, attrs: ['discount' => 50, 'total' => 450]);
    frItemLine($mixed, 'قرطاسية', 300);
    frItemLine($mixed, 'مشروبات', 200);

    $other = frInvoice($center, attrs: ['total' => 150]);
    frItemLine($other, 'قرطاسية', 150);

    // Unpaid invoice of the same category never counts.
    frItemLine(frInvoice($center, InvoiceStatus::Unpaid, ['total' => 999]), 'قرطاسية', 999);

    frExpense($center, ExpenseType::Operations, 1000, today()->toDateString());

    $totals = frReports()->unifiedTotals(['category' => 'قرطاسية']);

    expect($totals)->toBe(['income' => 450, 'expenses' => 0, 'net' => 450]);

    $rows = frReports()->unifiedRows(['category' => 'قرطاسية']);

    expect($rows->total())->toBe(2)
        ->and(collect($rows->items())->pluck('kind')->unique()->all())->toBe(['income'])
        ->and(collect($rows->items())->pluck('category')->unique()->all())->toBe(['قرطاسية'])
        ->and((int) collect($rows->items())->sum('amount'))->toBe($totals['income']);
});

it('reports only the chosen expense type and excludes income', function () {
    $center = testCenter();

    frItemLine(frInvoice($center, attrs: ['total' => 500]), null, 500);
    frExpense($center, ExpenseType::Operations, 120, today()->toDateString());
    frExpense($center, ExpenseType::Operations, 30, today()->toDateString());
    frExpense($center, ExpenseType::Transport, 999, today()->toDateString());

    $totals = frReports()->unifiedTotals(['category' => ExpenseType::Operations->value]);

    expect($totals)->toBe(['income' => 0, 'expenses' => 150, 'net' => -150]);

    $rows = frReports()->unifiedRows(['category' => ExpenseType::Operations->value]);

    expect($rows->total())->toBe(2)
        ->and(collect($rows->items())->pluck('kind')->unique()->all())->toBe(['expense'])
        ->and(collect($rows->items())->pluck('category')->unique()->all())->toBe([ExpenseType::Operations->value])
        ->and((int) collect($rows->items())->sum('amount'))->toBe($totals['expenses']);
});

it('returns nothing for contradictory movement and category filters', function () {
    $center = testCenter();

    frItemLine(frInvoice($center, attrs: ['total' => 500]), null, 500);
    frExpense($center, ExpenseType::Operations, 120, today()->toDateString());

    $filters = ['movement' => 'income', 'category' => ExpenseType::Operations->value];

    expect(frReports()->unifiedTotals($filters))->toBe(['income' => 0, 'expenses' => 0, 'net' => 0])
        ->and(frReports()->unifiedRows($filters)->total())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| unifiedRows
|--------------------------------------------------------------------------
*/

it('returns unified rows newest first with the documented shape', function () {
    $center = testCenter();

    $invoice = frInvoice($center, attrs: [
        'total' => 450,
        'beneficiary_name' => 'مريم',
        'created_at' => '2026-10-02 09:00:00',
    ]);
    frItemLine($invoice, 'مشروبات', 450);

    $older = frExpense($center, ExpenseType::Transport, 80, '2026-10-01');
    $older->update(['payee' => 'سائق']);
    $newer = frExpense($center, ExpenseType::Operations, 120, '2026-10-03');

    $rows = frReports()->unifiedRows([]);
    $items = collect($rows->items());

    expect($rows->total())->toBe(3)
        ->and($items->pluck('date')->all())->toBe(['2026-10-03', '2026-10-02', '2026-10-01']);

    [$first, $second, $third] = $items->all();

    expect($first->kind)->toBe('expense')
        ->and($first->reference)->toBe((string) $newer->id)
        ->and($first->category)->toBe(ExpenseType::Operations->value)
        ->and($first->amount)->toBe(120)
        ->and($first->center_id)->toBe($center->id);

    expect($second->kind)->toBe('income')
        ->and($second->reference)->toBe((string) $invoice->number)
        ->and($second->description)->toBe('مريم')
        ->and($second->category)->toBeNull()
        ->and($second->amount)->toBe(450);

    expect($third->kind)->toBe('expense')
        ->and($third->description)->toBe('سائق')
        ->and($third->amount)->toBe(80);
});

it('paginates unified rows and keeps the grand total intact', function () {
    $center = testCenter();

    foreach (range(1, 3) as $i) {
        frItemLine(frInvoice($center, attrs: ['total' => 100 * $i]), null, 100 * $i);
        frExpense($center, ExpenseType::Other, 10 * $i, today()->toDateString());
    }

    $page = frReports()->unifiedRows([], perPage: 4);

    expect($page->total())->toBe(6)
        ->and($page->lastPage())->toBe(2)
        ->and(count($page->items()))->toBe(4);

    $totals = frReports()->unifiedTotals([]);
    expect($totals)->toBe(['income' => 600, 'expenses' => 60, 'net' => 540]);
});

/*
|--------------------------------------------------------------------------
| todayStats
|--------------------------------------------------------------------------
*/

it('computes today stats including the low stock count', function () {
    $centerA = testCenter();
    $centerB = testCenter();

    // Two paid invoices today, one yesterday (out), one unpaid today (out).
    // Service lines, so no extra inventory items distort the low stock count.
    frServiceLine(frInvoice($centerA, attrs: ['total' => 500]), 'خدمات', 500);
    frServiceLine(frInvoice($centerA, attrs: ['total' => 300]), 'خدمات', 300);
    frServiceLine(frInvoice($centerA, attrs: [
        'total' => 777,
        'created_at' => now()->subDay(),
    ]), 'خدمات', 777);
    frServiceLine(frInvoice($centerA, InvoiceStatus::Unpaid, ['total' => 999]), 'خدمات', 999);

    frExpense($centerA, ExpenseType::Operations, 300, today()->toDateString());
    frExpense($centerA, ExpenseType::Operations, 50, today()->subDay()->toDateString());

    // Low: stock 3 <= threshold 5.
    $low = InventoryItem::factory()->create(['center_id' => $centerA->id, 'min_stock_threshold' => 5]);
    StockMovement::factory()->create(['inventory_item_id' => $low->id, 'center_id' => $centerA->id, 'quantity' => 3]);

    // Fine: stock 10 > threshold 5.
    $fine = InventoryItem::factory()->create(['center_id' => $centerA->id, 'min_stock_threshold' => 5]);
    StockMovement::factory()->create(['inventory_item_id' => $fine->id, 'center_id' => $centerA->id, 'quantity' => 10]);

    // Low but inactive — ignored.
    InventoryItem::factory()->create(['center_id' => $centerA->id, 'min_stock_threshold' => 5, 'is_active' => false]);

    // No movements at all → stock 0 <= threshold → low.
    InventoryItem::factory()->create(['center_id' => $centerA->id, 'min_stock_threshold' => 5]);

    // Low item in the other center.
    InventoryItem::factory()->create(['center_id' => $centerB->id, 'min_stock_threshold' => 5]);

    $stats = frReports()->todayStats([$centerA->id]);

    expect($stats)->toBe([
        'sales' => 800,
        'expenses' => 300,
        'net' => 500,
        'invoice_count' => 2,
        'low_stock_count' => 2,
    ]);

    // Empty center list = all centers.
    expect(frReports()->todayStats([])['low_stock_count'])->toBe(3);
});

/*
|--------------------------------------------------------------------------
| dailyRevenue
|--------------------------------------------------------------------------
*/

it('groups paid revenue by day, optionally per center', function () {
    $centerA = testCenter();
    $centerB = testCenter();

    frItemLine(frInvoice($centerA, attrs: ['total' => 100, 'created_at' => '2026-10-01 09:00:00']), null, 100);
    frItemLine(frInvoice($centerA, attrs: ['total' => 150, 'created_at' => '2026-10-01 18:00:00']), null, 150);
    frItemLine(frInvoice($centerA, attrs: ['total' => 200, 'created_at' => '2026-10-02 12:00:00']), null, 200);
    frItemLine(frInvoice($centerB, attrs: ['total' => 300, 'created_at' => '2026-10-01 10:00:00']), null, 300);

    // Outside the range, unpaid, cancelled — all out.
    frItemLine(frInvoice($centerA, attrs: ['total' => 500, 'created_at' => '2026-09-30 10:00:00']), null, 500);
    frItemLine(frInvoice($centerA, InvoiceStatus::Unpaid, ['total' => 999, 'created_at' => '2026-10-01 10:00:00']), null, 999);
    frItemLine(frInvoice($centerA, InvoiceStatus::Cancelled, ['total' => 888, 'created_at' => '2026-10-02 10:00:00']), null, 888);

    $daily = frReports()->dailyRevenue('2026-10-01', '2026-10-02', []);

    expect($daily->map(fn (object $row) => [$row->date, $row->center_id, $row->total])->all())->toBe([
        ['2026-10-01', null, 550],
        ['2026-10-02', null, 200],
    ]);

    $perCenter = frReports()->dailyRevenue('2026-10-01', '2026-10-02', [], perCenter: true);

    expect(
        $perCenter->map(fn (object $row) => [$row->date, $row->center_id, $row->total])
            ->sortBy(fn (array $row) => $row[0].'-'.$row[1])->values()->all()
    )->toBe([
        ['2026-10-01', $centerA->id, 250],
        ['2026-10-01', $centerB->id, 300],
        ['2026-10-02', $centerA->id, 200],
    ]);

    $onlyB = frReports()->dailyRevenue('2026-10-01', '2026-10-02', [$centerB->id]);

    expect($onlyB->map(fn (object $row) => [$row->date, $row->total])->all())
        ->toBe([['2026-10-01', 300]]);
});

/*
|--------------------------------------------------------------------------
| salesByCategory
|--------------------------------------------------------------------------
*/

it('buckets paid line totals by item category, service category, and أخرى', function () {
    $center = testCenter();

    $invoice = frInvoice($center, attrs: ['total' => 400]);
    frItemLine($invoice, 'قرطاسية', 120);
    frServiceLine($invoice, 'حجوزات', 200);
    frItemLine($invoice, null, 80); // no category → أخرى

    // Same categories on an unpaid invoice — excluded.
    $unpaid = frInvoice($center, InvoiceStatus::Unpaid, ['total' => 999]);
    frItemLine($unpaid, 'قرطاسية', 999);

    // Outside the date range — excluded.
    frItemLine(frInvoice($center, attrs: ['total' => 50, 'created_at' => '2020-01-01 10:00:00']), 'قرطاسية', 50);

    $from = today()->subDay()->toDateString();
    $to = today()->toDateString();

    $byCategory = frReports()->salesByCategory($from, $to, [$center->id])
        ->mapWithKeys(fn (object $row) => [$row->category => $row->total])
        ->all();

    expect($byCategory)->toBe([
        'حجوزات' => 200,
        'قرطاسية' => 120,
        'أخرى' => 80,
    ]);
});

/*
|--------------------------------------------------------------------------
| FinanceDisplay
|--------------------------------------------------------------------------
*/

it('formats amounts per the old-currency session toggle', function () {
    session()->forget(FinanceDisplay::SESSION_KEY);

    expect(FinanceDisplay::enabled())->toBeFalse()
        ->and(FinanceDisplay::format(1234))->toBe('1,234');

    FinanceDisplay::toggle();

    expect(FinanceDisplay::enabled())->toBeTrue()
        ->and(FinanceDisplay::format(1234))->toBe('123,400');

    FinanceDisplay::toggle();

    expect(FinanceDisplay::enabled())->toBeFalse()
        ->and(FinanceDisplay::format(0))->toBe('0');
});
