<?php

use App\Enums\Roles;
use App\Filament\Pages\FinancialReport;
use App\Filament\Pages\PointOfSale;
use App\Filament\Resources\CashShiftResource;
use App\Filament\Resources\ExpenseResource;
use App\Filament\Resources\InventoryItemResource;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Widgets\Finance\FinanceStatsOverview;
use App\Filament\Widgets\Finance\RevenueChart;
use App\Filament\Widgets\Finance\SalesByCategoryChart;
use App\Models\Invoice;
use App\Models\User;
use App\Support\FinanceAccess;

function userWithRole(Roles $role): User
{
    $center = testCenter();

    return User::factory()->create([
        'role' => $role->value,
        'city_id' => $center->city_id,
        'center_id' => $center->id,
    ]);
}

dataset('non general admin roles', [
    'city admin' => [Roles::CityAdmin],
    'center admin' => [Roles::CenterAdmin],
    'cashier' => [Roles::Cashier],
    'meal admin' => [Roles::MealAdmin],
    'observer' => [Roles::Observer],
]);

it('lets only the general admin through the finance gate', function () {
    expect(FinanceAccess::allowed(userWithRole(Roles::GeneralAdmin)))->toBeTrue();
});

it('refuses every other role at the finance gate', function (Roles $role) {
    expect(FinanceAccess::allowed(userWithRole($role)))->toBeFalse();
})->with('non general admin roles');

it('hides every finance resource, page and widget from non general admins', function (Roles $role) {
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
})->with('non general admin roles');

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

it('refuses invoice print and shift print routes to non general admins', function (Roles $role) {
    $user = userWithRole($role);
    $invoice = Invoice::factory()->create(['center_id' => $user->center_id]);
    $shift = openShift($user->center);

    $this->actingAs($user)
        ->get(route('pos.invoice.print', $invoice))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('cash-shifts.print', $shift))
        ->assertForbidden();
})->with('non general admin roles');

it('lets the general admin print an invoice', function () {
    $admin = userWithRole(Roles::GeneralAdmin);
    $invoice = Invoice::factory()->create(['center_id' => $admin->center_id]);

    $this->actingAs($admin)
        ->get(route('pos.invoice.print', $invoice))
        ->assertOk();
});
