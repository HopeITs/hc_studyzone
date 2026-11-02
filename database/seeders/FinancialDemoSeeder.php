<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CashShiftStatus;
use App\Enums\ExpenseType;
use App\Enums\InventoryUnit;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\Roles;
use App\Enums\StockMovementType;
use App\Models\CashShift;
use App\Models\Center;
use App\Models\City;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\LockedMonth;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\StockMovement;
use App\Models\Street;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo data for presenting the financial system to management.
 *
 * Seeds ONE demo center with a priced catalog, an inventory with opening
 * stock, and ~75 days of daily activity: cash shifts opened and closed with
 * reconciliation, invoices (paid / unpaid / cancelled, some linked to
 * students), expenses of every type, warehouse purchases that restock the
 * inventory, team allocations, and one older month locked via LockedMonth.
 *
 * Every number is produced the way the app produces it:
 * - invoice numbers are sequential per center (Invoice::nextNumberFor),
 * - invoice totals = sum of line totals - discount, never below zero,
 * - stock is only ever the sum of signed movements (purchases positive,
 *   sales/team allocations negative, cancellations reversed by adjustments),
 * - shift close snapshots equal the CashShift accessors: cash income = sum
 *   of PAID invoice totals on the shift, expenses = sum of its expenses,
 *   difference = counted - (opening + income - expenses).
 *
 * NOT part of the default seed run. Run it on demand only:
 *
 *   php artisan db:seed --class=FinancialDemoSeeder
 */
class FinancialDemoSeeder extends Seeder
{
    /** Days of history to generate, ending yesterday (today's shift stays open). */
    private const HISTORY_DAYS = 75;

    private const DEMO_PASSWORD = 'password';

    /** Running stock per inventory item id, mirrored from the movements written. */
    private array $stock = [];

    /** Next sequential invoice number for the demo center. */
    private int $nextInvoiceNumber = 1;

    public function run(): void
    {
        // Demo data must never land in a real database.
        if (app()->isProduction()) {
            return;
        }

        // Deterministic output: re-running on a fresh database always
        // produces the same demo story.
        mt_srand(20261102);

        $city = $this->seedCity();
        $center = $this->seedCenter($city);
        [$admin, $manager] = $this->seedUsers($city, $center);
        $servicePrices = $this->seedServiceCatalog($center);
        $items = $this->seedInventoryItems($center, $manager);
        $students = $this->seedStudents($city);

        // Activity is generated once: a center that already has invoices has
        // been seeded before, and re-generating would double every balance.
        if (Invoice::query()->where('center_id', $center->id)->exists()) {
            $this->seedLockedMonth($admin);
            $this->command?->warn('FinancialDemoSeeder: demo center already has invoices — activity generation skipped.');

            return;
        }

        $start = Carbon::today()->subDays(self::HISTORY_DAYS);

        $this->seedOpeningStock($center, $manager, $items, $start);
        $this->seedDailyActivity($center, $manager, $items, $servicePrices, $students, $start);
        $this->seedTodayOpenShift($center, $manager, $items, $servicePrices);
        $this->seedLockedMonth($admin);

        $this->command?->info('FinancialDemoSeeder: demo center "'.$center->name.'" seeded with '.self::HISTORY_DAYS.' days of activity.');
    }

    private function seedCity(): City
    {
        return City::query()->firstOrCreate(
            ['code' => 'demo-city'],
            ['name' => ['en' => 'Demo City', 'ar' => 'مدينة العرض']],
        );
    }

    private function seedCenter(City $city): Center
    {
        $center = Center::query()->where('city_id', $city->id)->first();

        return $center ?? Center::query()->create([
            'city_id' => $city->id,
            'name' => ['en' => 'Financial Demo Center', 'ar' => 'مركز العرض التجريبي'],
            'phone' => '0999999999',
            'address' => 'شارع العرض - مدينة العرض',
            'is_active' => true,
        ]);
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function seedUsers(City $city, Center $center): array
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin.demo@sz.com'],
            [
                'name' => 'مدير عام (عرض تجريبي)',
                'password' => Hash::make(self::DEMO_PASSWORD),
                'role' => Roles::GeneralAdmin,
                'is_active' => true,
            ],
        );

        $manager = User::query()->firstOrCreate(
            ['email' => 'manager.demo@sz.com'],
            [
                'name' => 'مسؤول مركز العرض',
                'password' => Hash::make(self::DEMO_PASSWORD),
                'role' => Roles::CenterAdmin,
                'city_id' => $city->id,
                'center_id' => $center->id,
                'is_active' => true,
            ],
        );

        User::query()->firstOrCreate(
            ['email' => 'cashier.demo@sz.com'],
            [
                'name' => 'كاشير مركز العرض',
                'password' => Hash::make(self::DEMO_PASSWORD),
                'role' => Roles::Cashier,
                'city_id' => $city->id,
                'center_id' => $center->id,
                'is_active' => true,
            ],
        );

        return [$admin, $manager];
    }

    /**
     * Price a realistic subset of the already-seeded service catalog for the
     * demo center. Prices follow the magnitudes in ServicePriceSeeder.
     *
     * @return array<int, int> service_id => price
     */
    private function seedServiceCatalog(Center $center): array
    {
        $priced = [
            'مرسم' => 15,
            'ميكراتور' => 50,
            'انترنت' => 50,
            'طباعة وجه اسود وابيض A4' => 2,
            'طباعة وجهين ابيض اسود A4' => 4,
            'طباعة ورق ملون A4 وجه' => 3,
            'طباعة ورق ملون A4 وجهين' => 5,
            'ورق ابيض A4' => 1,
            'راصور دفتر' => 70,
            'حجز قاعة سايلنت - ساعة' => 500,
        ];

        $prices = [];

        foreach ($priced as $serviceName => $price) {
            $service = Service::query()->where('name', $serviceName)->first();

            if ($service === null) {
                continue;
            }

            ServicePrice::query()->updateOrCreate(
                ['service_id' => $service->id, 'center_id' => $center->id],
                ['price' => $price, 'is_active' => true, 'start_date' => Carbon::today()->subYear()],
            );

            $prices[(int) $service->id] = $price;
        }

        return $prices;
    }

    /**
     * Buffet and stationery items with the cost/price/threshold a real
     * center would enter. Stock arrives later as purchase movements.
     *
     * @return array<int, InventoryItem>
     */
    private function seedInventoryItems(Center $center, User $manager): array
    {
        $catalog = [
            ['name' => 'مياه معدنية صغيرة', 'category' => 'مشروبات', 'unit' => InventoryUnit::Piece, 'purchase_cost' => 2, 'sale_price' => 5, 'min_stock_threshold' => 24, 'supplier' => 'شركة المياه الوطنية'],
            ['name' => 'عصير علبة', 'category' => 'مشروبات', 'unit' => InventoryUnit::Piece, 'purchase_cost' => 4, 'sale_price' => 8, 'min_stock_threshold' => 12, 'supplier' => 'مستودع الريان'],
            ['name' => 'قهوة سادة (كوب)', 'category' => 'مشروبات ساخنة', 'unit' => InventoryUnit::Piece, 'purchase_cost' => 3, 'sale_price' => 10, 'min_stock_threshold' => 20, 'supplier' => 'محمصة الشام'],
            ['name' => 'شاي (كوب)', 'category' => 'مشروبات ساخنة', 'unit' => InventoryUnit::Piece, 'purchase_cost' => 1, 'sale_price' => 5, 'min_stock_threshold' => 20, 'supplier' => 'مستودع الريان'],
            ['name' => 'نسكافيه (كوب)', 'category' => 'مشروبات ساخنة', 'unit' => InventoryUnit::Piece, 'purchase_cost' => 4, 'sale_price' => 12, 'min_stock_threshold' => 15, 'supplier' => 'محمصة الشام'],
            ['name' => 'بسكويت', 'category' => 'سناكس', 'unit' => InventoryUnit::Piece, 'purchase_cost' => 3, 'sale_price' => 6, 'min_stock_threshold' => 12, 'supplier' => 'مستودع الريان'],
            ['name' => 'شيبس', 'category' => 'سناكس', 'unit' => InventoryUnit::Piece, 'purchase_cost' => 3, 'sale_price' => 7, 'min_stock_threshold' => 12, 'supplier' => 'مستودع الريان'],
            ['name' => 'شوكولا', 'category' => 'سناكس', 'unit' => InventoryUnit::Piece, 'purchase_cost' => 5, 'sale_price' => 10, 'min_stock_threshold' => 12, 'supplier' => 'مستودع الريان'],
            ['name' => 'دفتر 60 ورقة', 'category' => 'قرطاسية', 'unit' => InventoryUnit::Piece, 'purchase_cost' => 15, 'sale_price' => 25, 'min_stock_threshold' => 10, 'supplier' => 'مكتبة الأمل'],
            ['name' => 'قلم حبر أزرق', 'category' => 'قرطاسية', 'unit' => InventoryUnit::Piece, 'purchase_cost' => 3, 'sale_price' => 6, 'min_stock_threshold' => 20, 'supplier' => 'مكتبة الأمل'],
            ['name' => 'رزمة ورق A4', 'category' => 'قرطاسية', 'unit' => InventoryUnit::Ream, 'purchase_cost' => 180, 'sale_price' => 240, 'min_stock_threshold' => 5, 'supplier' => 'مكتبة الأمل'],
            ['name' => 'ملف بلاستيكي', 'category' => 'قرطاسية', 'unit' => InventoryUnit::Piece, 'purchase_cost' => 4, 'sale_price' => 8, 'min_stock_threshold' => 15, 'supplier' => 'مكتبة الأمل'],
        ];

        $items = [];

        foreach ($catalog as $attributes) {
            $item = InventoryItem::query()->firstOrCreate(
                ['center_id' => $center->id, 'name' => $attributes['name']],
                $attributes + ['user_id' => $manager->id, 'is_active' => true],
            );

            // Mirror InventoryItem::currentStock(): stock is the sum of movements.
            $this->stock[(int) $item->id] = (int) $item->movements()->sum('quantity');

            $items[] = $item;
        }

        return $items;
    }

    /**
     * A few registered students so some invoices demo the student link.
     *
     * @return array<int, Student>
     */
    private function seedStudents(City $city): array
    {
        $street = Street::query()->where('city_id', $city->id)->first()
            ?? Street::query()->create([
                'city_id' => $city->id,
                'name' => ['en' => 'Demo Street', 'ar' => 'شارع العرض'],
            ]);

        $people = [
            ['firstname' => 'محمد', 'father_name' => 'خالد', 'lastname' => 'الأحمد', 'national_number' => '09900000001', 'phone' => '9930000001'],
            ['firstname' => 'آية', 'father_name' => 'سمير', 'lastname' => 'الحسن', 'national_number' => '09900000002', 'phone' => '9930000002'],
            ['firstname' => 'جورج', 'father_name' => 'ميشيل', 'lastname' => 'نصار', 'national_number' => '09900000003', 'phone' => '9930000003'],
            ['firstname' => 'لين', 'father_name' => 'عماد', 'lastname' => 'الخوري', 'national_number' => '09900000004', 'phone' => '9930000004'],
            ['firstname' => 'عمر', 'father_name' => 'أيمن', 'lastname' => 'العلي', 'national_number' => '09900000005', 'phone' => '9930000005'],
            ['firstname' => 'سارة', 'father_name' => 'فادي', 'lastname' => 'الديب', 'national_number' => '09900000006', 'phone' => '9930000006'],
        ];

        $students = [];

        foreach ($people as $index => $person) {
            $students[] = Student::query()->firstOrCreate(
                ['national_number' => $person['national_number']],
                $person + [
                    'street_id' => $street->id,
                    'gender' => $index % 2 === 0 ? 1 : 2,
                    'religion' => 1,
                    'year_of_birth' => Carbon::create(2000 + $index, 3, 15),
                    'is_student' => true,
                    'is_active' => true,
                ],
            );
        }

        return $students;
    }

    /**
     * Opening stock, entered the way the app enters it: one positive
     * purchase movement per item plus the purchase snapshot on the item,
     * exactly like InventoryItem::recordPurchase().
     *
     * @param  array<int, InventoryItem>  $items
     */
    private function seedOpeningStock(Center $center, User $manager, array $items, Carbon $start): void
    {
        $openedAt = $start->copy()->subDay()->setTime(10, 0);

        foreach ($items as $item) {
            $quantity = $item->unit === InventoryUnit::Ream
                ? mt_rand(15, 25)
                : mt_rand(80, 160);

            $this->recordPurchase($center, $manager, $item, $quantity, $openedAt, 'رصيد افتتاحي للمستودع');
        }
    }

    /**
     * One shift per working day: invoices, expenses, team allocations and a
     * reconciled close whose snapshots equal the CashShift accessors.
     *
     * @param  array<int, InventoryItem>  $items
     * @param  array<int, int>  $servicePrices
     * @param  array<int, Student>  $students
     */
    private function seedDailyActivity(
        Center $center,
        User $manager,
        array $items,
        array $servicePrices,
        array $students,
        Carbon $start,
    ): void {
        for ($day = 0; $day < self::HISTORY_DAYS; $day++) {
            $date = $start->copy()->addDays($day);

            // The center rests on Fridays.
            if ($date->isFriday()) {
                continue;
            }

            $shift = CashShift::query()->create([
                'center_id' => $center->id,
                'user_id' => $manager->id,
                'opened_at' => $date->copy()->setTime(9, 0),
                'opening_cash' => 500,
                'status' => CashShiftStatus::Open,
                'created_at' => $date->copy()->setTime(9, 0),
                'updated_at' => $date->copy()->setTime(9, 0),
            ]);

            $cashIncome = 0;
            $invoiceCount = mt_rand(4, 9);

            for ($i = 0; $i < $invoiceCount; $i++) {
                $invoice = $this->createInvoice(
                    $center,
                    $manager,
                    $shift,
                    $items,
                    $servicePrices,
                    $students,
                    $date->copy()->setTime(mt_rand(9, 19), mt_rand(0, 59)),
                );

                if ($invoice !== null && $invoice->status === InvoiceStatus::Paid) {
                    $cashIncome += (int) $invoice->total;
                }
            }

            $expensesTotal = $this->createDailyExpenses($center, $manager, $shift, $items, $date);

            // Every other week a bit of stock goes to the team (no revenue).
            if ($day % 14 === 6) {
                $this->createTeamAllocation($center, $manager, $items, $date->copy()->setTime(13, 30));
            }

            $this->closeShift($shift, $cashIncome, $expensesTotal, $date->copy()->setTime(20, 0));
        }
    }

    /**
     * Build one invoice the way PosService::createInvoice() + markPaid() do:
     * snapshot unit prices, line_total = quantity * unit_price,
     * total = max(0, subtotal - discount), sequential number per center,
     * and for a PAID invoice one negative sale movement per inventory line.
     * A paid-then-cancelled invoice gets reversing positive adjustments,
     * exactly like Invoice::markCancelled().
     *
     * @param  array<int, InventoryItem>  $items
     * @param  array<int, int>  $servicePrices
     * @param  array<int, Student>  $students
     */
    private function createInvoice(
        Center $center,
        User $manager,
        ?CashShift $shift,
        array $items,
        array $servicePrices,
        array $students,
        Carbon $time,
    ): ?Invoice {
        $lines = $this->buildInvoiceLines($items, $servicePrices);

        if ($lines === []) {
            return null;
        }

        $subtotal = 0;

        foreach ($lines as $line) {
            $subtotal += $line['line_total'];
        }

        // Occasional small discount, clamped the way PosService clamps it.
        $discount = mt_rand(1, 10) === 1 ? min(mt_rand(5, 25), $subtotal) : 0;
        $total = max(0, $subtotal - $discount);

        // ~70% paid, ~15% unpaid (to collect later), ~15% cancelled.
        $roll = mt_rand(1, 100);
        $status = match (true) {
            $roll <= 70 => InvoiceStatus::Paid,
            $roll <= 85 => InvoiceStatus::Unpaid,
            default => InvoiceStatus::Cancelled,
        };

        // Half of the cancelled ones were paid first, then reversed.
        $wasPaidBeforeCancel = $status === InvoiceStatus::Cancelled && mt_rand(0, 1) === 1;

        // A third of the invoices belong to a registered student; the
        // beneficiary name is snapshotted server-side from the student.
        $student = $students !== [] && mt_rand(1, 3) === 1 ? $students[array_rand($students)] : null;
        $beneficiary = $student?->getFilamentName() ?? $this->walkInName();

        $invoice = Invoice::query()->create([
            'center_id' => $center->id,
            'user_id' => $manager->id,
            'number' => $this->nextInvoiceNumber++,
            'status' => $status,
            'beneficiary_name' => $beneficiary,
            'student_id' => $student?->id,
            'discount' => $discount,
            'total' => $total,
            'payment_method' => PaymentMethod::Cash,
            'shift_id' => $status === InvoiceStatus::Paid || $wasPaidBeforeCancel ? $shift?->id : null,
            'note' => $status === InvoiceStatus::Cancelled ? 'ألغيت بطلب من المستفيد' : null,
            'created_at' => $time,
            'updated_at' => $time,
        ]);

        foreach ($lines as $line) {
            $invoice->items()->create([
                'inventory_item_id' => $line['inventory_item_id'],
                'service_id' => $line['service_id'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'line_total' => $line['line_total'],
                'created_at' => $time,
                'updated_at' => $time,
            ]);

            if ($line['inventory_item_id'] === null) {
                continue;
            }

            if ($status === InvoiceStatus::Paid || $wasPaidBeforeCancel) {
                // Collection deducts stock: negative sale movement per line.
                $this->recordMovement(
                    $center,
                    $manager,
                    $line['inventory_item_id'],
                    StockMovementType::Sale,
                    -$line['quantity'],
                    $time,
                    invoiceId: (int) $invoice->id,
                    note: __('invoices.notes.sale', ['number' => $invoice->number]),
                );
            }

            if ($wasPaidBeforeCancel) {
                // Cancellation reverses with a positive adjustment, keeping
                // the movement log append-only.
                $this->recordMovement(
                    $center,
                    $manager,
                    $line['inventory_item_id'],
                    StockMovementType::Adjustment,
                    $line['quantity'],
                    $time->copy()->addMinutes(20),
                    invoiceId: (int) $invoice->id,
                    note: __('invoices.notes.cancellation', ['number' => $invoice->number]),
                );
            }
        }

        return $invoice;
    }

    /**
     * 1-3 lines, each selling exactly one of: a priced service or an
     * inventory item with enough stock left.
     *
     * @param  array<int, InventoryItem>  $items
     * @param  array<int, int>  $servicePrices
     * @return array<int, array{inventory_item_id: ?int, service_id: ?int, quantity: int, unit_price: int, line_total: int}>
     */
    private function buildInvoiceLines(array $items, array $servicePrices): array
    {
        $lines = [];
        $lineCount = mt_rand(1, 3);

        for ($i = 0; $i < $lineCount; $i++) {
            if ($servicePrices !== [] && mt_rand(1, 100) <= 60) {
                $serviceId = (int) array_rand($servicePrices);
                $price = $servicePrices[$serviceId];
                // Cheap paper services sell in bundles, dear ones once.
                $quantity = $price <= 5 ? mt_rand(5, 40) : mt_rand(1, 3);

                $lines[] = [
                    'inventory_item_id' => null,
                    'service_id' => $serviceId,
                    'quantity' => $quantity,
                    'unit_price' => $price,
                    'line_total' => $quantity * $price,
                ];

                continue;
            }

            $item = $items[array_rand($items)];
            $itemId = (int) $item->id;
            $available = $this->stock[$itemId] ?? 0;

            if ($available < 1) {
                continue;
            }

            $quantity = min(mt_rand(1, 4), $available);

            $lines[] = [
                'inventory_item_id' => $itemId,
                'service_id' => null,
                'quantity' => $quantity,
                'unit_price' => (int) $item->sale_price,
                'line_total' => $quantity * (int) $item->sale_price,
            ];
        }

        return $lines;
    }

    /**
     * 1-3 expenses on the day's shift. A warehouse purchase (every few days,
     * or sooner when stock runs low) also writes the linked purchase
     * movements, exactly like ExpenseService::syncWarehouseMovements().
     *
     * @param  array<int, InventoryItem>  $items
     * @return int total spent on the shift
     */
    private function createDailyExpenses(Center $center, User $manager, CashShift $shift, array $items, Carbon $date): int
    {
        $total = 0;

        $catalog = [
            [ExpenseType::Operations, 'فواتير كهرباء وانترنت', 'شركة الكهرباء', 80, 250],
            [ExpenseType::Hospitality, 'ضيافة زوار المركز', 'كافتيريا الجوار', 20, 80],
            [ExpenseType::Transport, 'نقل مستلزمات وتوصيل', 'مكتب نقل السلام', 30, 120],
            [ExpenseType::Maintenance, 'صيانة طابعة وتكييف', 'ورشة النور للصيانة', 100, 400],
            [ExpenseType::Advance, 'سلفة موظف', 'موظف المركز', 100, 300],
            [ExpenseType::Other, 'مصاريف نثرية', null, 10, 60],
        ];

        $count = mt_rand(1, 3);

        for ($i = 0; $i < $count; $i++) {
            [$type, $note, $payee, $min, $max] = $catalog[array_rand($catalog)];
            $time = $date->copy()->setTime(mt_rand(10, 18), mt_rand(0, 59));

            Expense::query()->create([
                'center_id' => $center->id,
                'user_id' => $manager->id,
                'type' => $type,
                'payee' => $payee,
                'amount' => $amount = mt_rand($min, $max),
                'date' => $date->toDateString(),
                'note' => $note,
                'shift_id' => $shift->id,
                'created_at' => $time,
                'updated_at' => $time,
            ]);

            $total += $amount;
        }

        $total += $this->maybeRestockWarehouse($center, $manager, $shift, $items, $date);

        return $total;
    }

    /**
     * Restock items that fell to (or below) their threshold through a
     * warehouse-purchase expense whose amount equals the sum of its linked
     * purchase movements' costs — the invariant ExpenseService keeps.
     *
     * @param  array<int, InventoryItem>  $items
     */
    private function maybeRestockWarehouse(Center $center, User $manager, CashShift $shift, array $items, Carbon $date): int
    {
        $lowItems = array_values(array_filter(
            $items,
            fn (InventoryItem $item): bool => ($this->stock[(int) $item->id] ?? 0) <= (int) $item->min_stock_threshold,
        ));

        if ($lowItems === []) {
            return 0;
        }

        $time = $date->copy()->setTime(11, 15);
        $lines = [];
        $amount = 0;

        foreach (array_slice($lowItems, 0, 4) as $item) {
            $quantity = $item->unit === InventoryUnit::Ream ? mt_rand(10, 15) : mt_rand(40, 80);
            $cost = $quantity * (int) $item->purchase_cost;

            $lines[] = ['item' => $item, 'quantity' => $quantity, 'cost' => $cost];
            $amount += $cost;
        }

        $expense = Expense::query()->create([
            'center_id' => $center->id,
            'user_id' => $manager->id,
            'type' => ExpenseType::WarehousePurchase,
            'payee' => 'مستودع الريان',
            'amount' => $amount,
            'date' => $date->toDateString(),
            'note' => 'تعبئة مستودع البوفيه والقرطاسية',
            'shift_id' => $shift->id,
            'created_at' => $time,
            'updated_at' => $time,
        ]);

        foreach ($lines as $line) {
            $this->recordPurchase(
                $center,
                $manager,
                $line['item'],
                $line['quantity'],
                $time,
                __('finance.notes.warehouse_expense', ['payee' => (string) $expense->payee]),
                expenseId: (int) $expense->id,
            );
        }

        return $amount;
    }

    /**
     * Hand a little stock to the team: deducting movements with no invoice
     * and no revenue, like PosService::recordTeamAllocation().
     *
     * @param  array<int, InventoryItem>  $items
     */
    private function createTeamAllocation(Center $center, User $manager, array $items, Carbon $time): void
    {
        foreach (array_slice($items, 0, 2) as $item) {
            $itemId = (int) $item->id;
            $quantity = min(mt_rand(2, 5), $this->stock[$itemId] ?? 0);

            if ($quantity < 1) {
                continue;
            }

            $this->recordMovement(
                $center,
                $manager,
                $itemId,
                StockMovementType::TeamAllocation,
                -$quantity,
                $time,
                note: __('pos.notes.team_allocation', ['recipient' => 'فريق المركز']),
            );
        }
    }

    /**
     * Close the shift with the same snapshots CashShift::close() computes:
     * income from PAID invoices only, expected = opening + income - expenses,
     * difference = counted - expected (a non-zero difference carries a note).
     */
    private function closeShift(CashShift $shift, int $cashIncome, int $expensesTotal, Carbon $closedAt): void
    {
        $expectedCash = (int) $shift->opening_cash + $cashIncome - $expensesTotal;

        // Most days reconcile exactly; now and then the drawer is short or
        // over by a few units and the cashier explains it.
        $difference = mt_rand(1, 6) === 1 ? mt_rand(-15, 15) : 0;
        $countedCash = $expectedCash + $difference;

        $shift->forceFill([
            'cash_income' => $cashIncome,
            'expenses_total' => $expensesTotal,
            'counted_cash' => $countedCash,
            'difference' => $difference,
            'closing_note' => $difference === 0 ? null : ($difference > 0 ? 'زيادة بسيطة في الصندوق - فراطة غير مسجلة' : 'عجز بسيط - فرق فراطة مع الزبائن'),
            'closed_at' => $closedAt,
            'status' => CashShiftStatus::Closed,
            'updated_at' => $closedAt,
        ])->save();
    }

    /**
     * Today's shift stays OPEN with a little fresh activity, so the live
     * expected-cash accessors have something to show during the demo.
     *
     * @param  array<int, InventoryItem>  $items
     * @param  array<int, int>  $servicePrices
     */
    private function seedTodayOpenShift(Center $center, User $manager, array $items, array $servicePrices): void
    {
        $today = Carbon::today();

        if ($today->isFriday()) {
            return;
        }

        $shift = CashShift::query()->create([
            'center_id' => $center->id,
            'user_id' => $manager->id,
            'opened_at' => $today->copy()->setTime(9, 0),
            'opening_cash' => 500,
            'status' => CashShiftStatus::Open,
        ]);

        for ($i = 0; $i < 3; $i++) {
            $this->createInvoice(
                $center,
                $manager,
                $shift,
                $items,
                $servicePrices,
                [],
                $today->copy()->setTime(9 + $i, 30),
            );
        }
    }

    /**
     * Lock the month before last, so management sees month-locking refuse
     * edits on old records while recent months stay editable.
     */
    private function seedLockedMonth(User $admin): void
    {
        $period = Carbon::today()->subMonthsNoOverflow(2);

        LockedMonth::query()->updateOrCreate(
            ['year' => $period->year, 'month' => $period->month],
            [
                'is_locked' => true,
                'locked_by' => $admin->id,
                'locked_at' => $period->copy()->endOfMonth()->addDays(3),
            ],
        );
    }

    /**
     * One positive purchase movement plus the item's purchase snapshot,
     * mirroring InventoryItem::recordPurchase().
     */
    private function recordPurchase(
        Center $center,
        User $manager,
        InventoryItem $item,
        int $quantity,
        Carbon $time,
        string $note,
        ?int $expenseId = null,
    ): void {
        $cost = $quantity * (int) $item->purchase_cost;

        $this->recordMovement(
            $center,
            $manager,
            (int) $item->id,
            StockMovementType::Purchase,
            $quantity,
            $time,
            cost: $cost,
            expenseId: $expenseId,
            note: $note,
        );

        $item->update([
            'last_purchase_date' => $time->toDateString(),
            'purchase_cost' => (int) round($cost / max(1, $quantity)),
        ]);
    }

    /**
     * Write one signed movement and keep the in-memory running stock in
     * sync, so later sales never draw the log below zero.
     */
    private function recordMovement(
        Center $center,
        User $manager,
        int $itemId,
        StockMovementType $type,
        int $quantity,
        Carbon $time,
        ?int $cost = null,
        ?int $invoiceId = null,
        ?int $expenseId = null,
        ?string $note = null,
    ): void {
        StockMovement::query()->create([
            'center_id' => $center->id,
            'user_id' => $manager->id,
            'inventory_item_id' => $itemId,
            'type' => $type,
            'quantity' => $quantity,
            'cost' => $cost,
            'invoice_id' => $invoiceId,
            'expense_id' => $expenseId,
            'note' => $note,
            'created_at' => $time,
            'updated_at' => $time,
        ]);

        $this->stock[$itemId] = ($this->stock[$itemId] ?? 0) + $quantity;
    }

    /** Arabic walk-in beneficiary names for invoices without a student link. */
    private function walkInName(): string
    {
        $first = ['أحمد', 'ريم', 'كريم', 'هبة', 'طارق', 'نور', 'سليم', 'رنا', 'مايا', 'فارس'];
        $last = ['الشامي', 'حداد', 'العمر', 'قصاب', 'الزين', 'مرعي', 'سليمان', 'عبود'];

        return $first[array_rand($first)].' '.$last[array_rand($last)];
    }
}
