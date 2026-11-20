<?php

use App\Enums\ExpenseType;
use App\Enums\Roles;
use App\Filament\Pages\FinancialReport;
use App\Filament\Pages\PointOfSale;
use App\Filament\Resources\CashShiftResource;
use App\Filament\Resources\ExpenseResource;
use App\Filament\Resources\ExpenseResource\Pages\CreateExpense;
use App\Filament\Resources\InventoryItemResource;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\InvoiceResource\Pages\CreateInvoice;
use App\Filament\Widgets\Finance\FinanceStatsOverview;
use App\Filament\Widgets\Finance\RevenueChart;
use App\Filament\Widgets\Finance\SalesByCategoryChart;
use App\Models\Center;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\User;
use App\Support\FinanceAccess;
use Filament\Facades\Filament;
use Livewire\Livewire;

function userWithRole(Roles $role): User
{
    $center = testCenter();

    return User::factory()->create([
        'role' => $role->value,
        'city_id' => $center->city_id,
        'center_id' => $center->id,
    ]);
}

dataset('roles without finance access', [
    'center admin' => [Roles::CenterAdmin],
    'cashier' => [Roles::Cashier],
    'meal admin' => [Roles::MealAdmin],
    'observer' => [Roles::Observer],
]);

it('lets the general admin through the finance gate', function () {
    expect(FinanceAccess::allowed(userWithRole(Roles::GeneralAdmin)))->toBeTrue();
});

it('refuses every other role at the finance gate', function (Roles $role) {
    expect(FinanceAccess::allowed(userWithRole($role)))->toBeFalse();
})->with('roles without finance access');

it('hides every finance resource, page and widget from roles without finance access', function (Roles $role) {
    $this->actingAs(userWithRole($role));

    expect(InvoiceResource::canViewAny())->toBeFalse()
        ->and(ExpenseResource::canViewAny())->toBeFalse()
        ->and(InventoryItemResource::canViewAny())->toBeFalse()
        ->and(CashShiftResource::canViewAny())->toBeFalse()
        ->and(InvoiceResource::canCreate())->toBeFalse()
        ->and(ExpenseResource::canCreate())->toBeFalse()
        ->and(InventoryItemResource::canCreate())->toBeFalse()
        ->and(PointOfSale::canAccess())->toBeFalse()
        ->and(FinancialReport::canAccess())->toBeFalse()
        ->and(FinanceStatsOverview::canView())->toBeFalse()
        ->and(RevenueChart::canView())->toBeFalse()
        ->and(SalesByCategoryChart::canView())->toBeFalse();
})->with('roles without finance access');

it('keeps every finance screen open for the general admin', function () {
    $this->actingAs(userWithRole(Roles::GeneralAdmin));

    expect(InvoiceResource::canViewAny())->toBeTrue()
        ->and(ExpenseResource::canViewAny())->toBeTrue()
        ->and(InventoryItemResource::canViewAny())->toBeTrue()
        ->and(CashShiftResource::canViewAny())->toBeTrue()
        ->and(PointOfSale::canAccess())->toBeTrue()
        ->and(FinancialReport::canAccess())->toBeTrue()
        ->and(FinanceStatsOverview::canView())->toBeTrue();
});

it('refuses invoice print and shift print routes to roles without finance access', function (Roles $role) {
    $user = userWithRole($role);
    $invoice = Invoice::factory()->create(['center_id' => $user->center_id]);
    $shift = openShift($user->center);

    $this->actingAs($user)
        ->get(route('pos.invoice.print', $invoice))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('cash-shifts.print', $shift))
        ->assertForbidden();
})->with('roles without finance access');

it('lets the general admin print an invoice', function () {
    $admin = userWithRole(Roles::GeneralAdmin);
    $invoice = Invoice::factory()->create(['center_id' => $admin->center_id]);

    $this->actingAs($admin)
        ->get(route('pos.invoice.print', $invoice))
        ->assertOk();
});

it('lets a city admin view finance and create expenses and invoices within their city', function () {
    $own = testCenter();
    $second = Center::factory()->create(['city_id' => $own->city_id]);
    $foreign = testCenter();
    $admin = User::factory()->create(['role' => Roles::CityAdmin(), 'city_id' => $own->city_id]);

    $this->actingAs($admin);

    expect(FinanceAccess::allowed())->toBeTrue()
        ->and(InvoiceResource::canViewAny())->toBeTrue()
        ->and(ExpenseResource::canViewAny())->toBeTrue()
        ->and(InventoryItemResource::canViewAny())->toBeTrue()
        ->and(CashShiftResource::canViewAny())->toBeTrue()
        ->and(FinancialReport::canAccess())->toBeTrue()
        ->and(FinanceStatsOverview::canView())->toBeTrue()
        ->and(InvoiceResource::canCreate())->toBeTrue()
        ->and(ExpenseResource::canCreate())->toBeTrue()
        ->and(InventoryItemResource::canCreate())->toBeFalse()
        ->and(PointOfSale::canAccess())->toBeFalse();

    $invoice = Invoice::factory()->create(['center_id' => $second->id]);
    $foreignInvoice = Invoice::factory()->create(['center_id' => $foreign->id]);

    expect(InvoiceResource::getEloquentQuery()->pluck('id')->all())->toBe([$invoice->id])
        ->and(InvoiceResource::canEdit($invoice))->toBeFalse();

    $this->get(route('pos.invoice.print', $invoice))->assertOk();
    $this->get(route('pos.invoice.print', $foreignInvoice))->assertForbidden();
});

it('saves entries for a center in the city and rejects a submitted foreign center', function () {
    $own = testCenter();
    $foreign = testCenter();
    openShift($own);
    openShift($foreign);
    $item = InventoryItem::factory()->create(['center_id' => $own->id]);

    $admin = User::factory()->create(['role' => Roles::CityAdmin(), 'city_id' => $own->city_id]);
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $expense = [
        'type' => ExpenseType::Operations->value,
        'payee' => 'Test payee',
        'amount' => 100,
        'date' => today()->toDateString(),
    ];

    Livewire::test(CreateExpense::class)
        ->fillForm(['center_id' => $foreign->id] + $expense)
        ->call('create')
        ->assertHasFormErrors(['center_id']);

    Livewire::test(CreateExpense::class)
        ->fillForm(['center_id' => $own->id] + $expense)
        ->call('create')
        ->assertHasNoFormErrors();

    $invoice = [
        'beneficiary_name' => 'Test beneficiary',
        'items' => [['kind' => 'inventory', 'inventory_item_id' => $item->id, 'quantity' => 1, 'unit_price' => $item->sale_price]],
    ];

    Livewire::test(CreateInvoice::class)
        ->fillForm(['center_id' => $foreign->id] + $invoice)
        ->call('create')
        ->assertHasFormErrors(['center_id']);

    Livewire::test(CreateInvoice::class)
        ->fillForm(['center_id' => $own->id] + $invoice)
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Expense::query()->pluck('center_id')->all())->toBe([$own->id])
        ->and(Invoice::query()->pluck('center_id')->all())->toBe([$own->id]);
});
