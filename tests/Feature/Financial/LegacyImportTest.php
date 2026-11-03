<?php

use App\Enums\ExpenseType;
use App\Enums\InvoiceStatus;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\LockedMonth;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Builds an in-memory SQLite stand-in for an attached DBRestaurantProV5
 * database, using the table/column names from config/legacy-finance.php.
 */
function legacyConnection(): string
{
    config(['database.connections.legacy_test' => [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]]);

    $schema = Schema::connection('legacy_test');

    $schema->create('Items', function ($table) {
        $table->integer('ItemID')->primary();
        $table->string('ItemName');
        $table->string('CategoryName')->nullable();
        $table->float('BuyPrice')->default(0);
        $table->float('SalePrice')->default(0);
        $table->integer('Quantity')->default(0);
    });

    $schema->create('Bills', function ($table) {
        $table->integer('BillID')->primary();
        $table->string('BillDate')->nullable();
        $table->string('CustomerName')->nullable();
        $table->float('Total')->default(0);
        $table->float('Discount')->default(0);
        $table->string('Notes')->nullable();
    });

    $schema->create('BillItems', function ($table) {
        $table->integer('BillID');
        $table->integer('ItemID');
        $table->integer('Qty');
        $table->float('Price');
    });

    $schema->create('Expenses', function ($table) {
        $table->integer('ExpenseID')->primary();
        $table->string('ExpDate')->nullable();
        $table->string('ExpType')->nullable();
        $table->string('Payee')->nullable();
        $table->float('Amount')->default(0);
        $table->string('Notes')->nullable();
    });

    return 'legacy_test';
}

function seedLegacy(): void
{
    $db = DB::connection('legacy_test');

    // Old-currency prices: 50000 old = 500 new.
    $db->table('Items')->insert([
        ['ItemID' => 1, 'ItemName' => 'دفتر', 'CategoryName' => 'قرطاسية', 'BuyPrice' => 50000, 'SalePrice' => 70000, 'Quantity' => 12],
        ['ItemID' => 2, 'ItemName' => 'قلم', 'CategoryName' => null, 'BuyPrice' => 10000, 'SalePrice' => 15000, 'Quantity' => 0],
    ]);

    $db->table('Bills')->insert([
        ['BillID' => 10, 'BillDate' => '2024-05-10', 'CustomerName' => 'طالب قديم', 'Total' => 140000, 'Discount' => 0, 'Notes' => null],
        ['BillID' => 11, 'BillDate' => '2024-06-01', 'CustomerName' => null, 'Total' => 15000, 'Discount' => 5000, 'Notes' => 'ملاحظة'],
    ]);

    $db->table('BillItems')->insert([
        ['BillID' => 10, 'ItemID' => 1, 'Qty' => 2, 'Price' => 70000],
        ['BillID' => 11, 'ItemID' => 2, 'Qty' => 1, 'Price' => 15000],
        ['BillID' => 11, 'ItemID' => 999, 'Qty' => 1, 'Price' => 5000], // unknown item
    ]);

    $db->table('Expenses')->insert([
        ['ExpenseID' => 5, 'ExpDate' => '2024-05-20', 'ExpType' => 'صيانة', 'Payee' => 'ورشة', 'Amount' => 30000, 'Notes' => null],
        ['ExpenseID' => 6, 'ExpDate' => '2024-05-25', 'ExpType' => 'كهرباء', 'Payee' => null, 'Amount' => 20000, 'Notes' => null],
    ]);
}

it('imports items, opening stock, invoices and expenses with currency conversion', function () {
    $center = testCenter();
    legacyConnection();
    seedLegacy();

    $this->artisan('finance:import-legacy', ['center' => $center->id, 'connection' => 'legacy_test'])
        ->assertSuccessful();

    // Items: old prices divided by 100.
    $notebook = InventoryItem::where('legacy_key', 'item:1')->firstOrFail();
    expect($notebook->purchase_cost)->toBe(500)
        ->and($notebook->sale_price)->toBe(700)
        ->and($notebook->current_stock)->toBe(12);

    // Zero-stock item gets no opening movement.
    expect(StockMovement::where('legacy_key', 'opening:2')->exists())->toBeFalse();

    // Invoices: Paid, dated from legacy, totals converted.
    $bill = Invoice::where('legacy_key', 'invoice:10')->firstOrFail();
    expect($bill->status)->toBe(InvoiceStatus::Paid)
        ->and($bill->total)->toBe(1400)
        ->and($bill->created_at->toDateString())->toBe('2024-05-10')
        ->and($bill->items)->toHaveCount(1)
        ->and($bill->items->first()->unit_price)->toBe(700);

    // Missing beneficiary falls back to "-", discount converted.
    $bill11 = Invoice::where('legacy_key', 'invoice:11')->firstOrFail();
    expect($bill11->beneficiary_name)->toBe('-')
        ->and($bill11->discount)->toBe(50)
        ->and($bill11->items)->toHaveCount(1); // unknown item line skipped

    // Expenses: mapped type, unknown label becomes Other + original in note.
    $mapped = Expense::where('legacy_key', 'expense:5')->firstOrFail();
    expect($mapped->type)->toBe(ExpenseType::Maintenance)
        ->and($mapped->amount)->toBe(300);

    $other = Expense::where('legacy_key', 'expense:6')->firstOrFail();
    expect($other->type)->toBe(ExpenseType::Other)
        ->and($other->note)->toContain('كهرباء');
});

it('re-running the import duplicates nothing', function () {
    $center = testCenter();
    legacyConnection();
    seedLegacy();

    $this->artisan('finance:import-legacy', ['center' => $center->id, 'connection' => 'legacy_test'])
        ->assertSuccessful();
    $this->artisan('finance:import-legacy', ['center' => $center->id, 'connection' => 'legacy_test'])
        ->assertSuccessful();

    expect(InventoryItem::where('center_id', $center->id)->count())->toBe(2)
        ->and(Invoice::where('center_id', $center->id)->count())->toBe(2)
        ->and(Expense::where('center_id', $center->id)->count())->toBe(2)
        ->and(StockMovement::where('center_id', $center->id)->count())->toBe(1);
});

it('converts only amounts dated before the cutoff', function () {
    $center = testCenter();
    legacyConnection();
    seedLegacy();

    // Bill 11 (2024-06-01) is on/after the cutoff: already new currency.
    $this->artisan('finance:import-legacy', [
        'center' => $center->id,
        'connection' => 'legacy_test',
        '--cutoff' => '2024-06-01',
    ])->assertSuccessful();

    expect(Invoice::where('legacy_key', 'invoice:10')->firstOrFail()->total)->toBe(1400)
        ->and(Invoice::where('legacy_key', 'invoice:11')->firstOrFail()->total)->toBe(15000);
});

it('imports historical rows that fall in locked months', function () {
    $center = testCenter();
    legacyConnection();
    seedLegacy();

    LockedMonth::create(['year' => 2024, 'month' => 5, 'is_locked' => true, 'locked_at' => now()]);

    $this->artisan('finance:import-legacy', ['center' => $center->id, 'connection' => 'legacy_test'])
        ->assertSuccessful();

    expect(Invoice::where('legacy_key', 'invoice:10')->exists())->toBeTrue()
        ->and(Expense::where('legacy_key', 'expense:5')->exists())->toBeTrue();
});

it('saves nothing on a dry run', function () {
    $center = testCenter();
    legacyConnection();
    seedLegacy();

    $this->artisan('finance:import-legacy', [
        'center' => $center->id,
        'connection' => 'legacy_test',
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(InventoryItem::count())->toBe(0)
        ->and(Invoice::count())->toBe(0)
        ->and(Expense::count())->toBe(0);
});

it('fails loudly when the mapped schema does not match', function () {
    $center = testCenter();
    config(['database.connections.legacy_test' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ]]);

    $this->artisan('finance:import-legacy', ['center' => $center->id, 'connection' => 'legacy_test'])
        ->assertFailed();
});
