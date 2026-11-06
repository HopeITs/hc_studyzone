<?php

use App\Enums\Roles;
use App\Filament\Pages\PointOfSale;
use App\Filament\Pages\Reports;
use App\Filament\Resources\CashShiftResource;
use App\Filament\Resources\ExpenseResource;
use App\Filament\Resources\InventoryItemResource;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\ServiceResource;
use App\Filament\Resources\UserResource;
use App\Models\Center;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\User;
use App\Services\PosService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

function cashierFor(Center $center): User
{
    return User::factory()->create([
        'role' => Roles::Cashier(),
        'city_id' => $center->city_id,
        'center_id' => $center->id,
    ]);
}

function centerAdminFor(Center $center): User
{
    return User::factory()->create([
        'role' => Roles::CenterAdmin(),
        'city_id' => $center->city_id,
        'center_id' => $center->id,
    ]);
}

it('scopes cashier invoice, expense, shift and inventory queries to their own center', function () {
    $own = testCenter();
    $other = testCenter();

    Invoice::factory()->create(['center_id' => $own->id]);
    Invoice::factory()->create(['center_id' => $other->id]);

    Expense::factory()->create(['center_id' => $own->id]);
    Expense::factory()->create(['center_id' => $other->id]);

    InventoryItem::factory()->create(['center_id' => $own->id]);
    InventoryItem::factory()->create(['center_id' => $other->id]);

    openShift($own);
    openShift($other);

    $this->actingAs(cashierFor($own));

    foreach ([
        InvoiceResource::class,
        ExpenseResource::class,
        InventoryItemResource::class,
        CashShiftResource::class,
    ] as $resource) {
        $centerIds = $resource::getEloquentQuery()->pluck('center_id')->unique()->values()->all();

        expect($centerIds)->toBe([$own->id]);
    }
});

it('denies the cashier role everything outside the POS surface', function () {
    $center = testCenter();

    $this->actingAs(cashierFor($center));

    expect(ServiceResource::canViewAny())->toBeFalse()
        ->and(UserResource::canViewAny())->toBeFalse()
        ->and(Reports::canAccess())->toBeFalse()
        ->and(InvoiceResource::canCreate())->toBeFalse()
        ->and(InventoryItemResource::canCreate())->toBeFalse();
});

it('ignores a tampered POS centerId for center-bound roles', function () {
    $own = testCenter();
    $other = testCenter();

    foreach ([cashierFor($own), centerAdminFor($own)] as $user) {
        $this->actingAs($user);

        $page = new PointOfSale;
        $page->centerId = $other->id;

        expect($page->effectiveCenterId())->toBe($own->id);
    }
});

it('refuses a POS invoice written by a center-bound user to another center', function () {
    $own = testCenter();
    $other = testCenter();
    $cashier = cashierFor($own);

    expect(fn () => app(PosService::class)->createInvoice(
        centerId: $other->id,
        lines: [],
        beneficiaryName: 'مستفيد',
        studentNumber: null,
        discount: 0,
        note: null,
        collect: false,
        userId: $cashier->id,
    ))->toThrow(ValidationException::class);
});

it('refuses a POS team allocation by a center-bound user for another center', function () {
    $own = testCenter();
    $other = testCenter();
    $admin = centerAdminFor($own);

    expect(fn () => app(PosService::class)->recordTeamAllocation(
        centerId: $other->id,
        lines: [],
        recipient: 'الفريق',
        note: null,
        userId: $admin->id,
    ))->toThrow(ValidationException::class);
});

it('blocks a city admin of another city from printing an invoice', function () {
    $center = testCenter();
    $otherCityCenter = testCenter();

    $invoice = Invoice::factory()->create(['center_id' => $center->id]);

    $foreignCityAdmin = User::factory()->create([
        'role' => Roles::CityAdmin(),
        'city_id' => $otherCityCenter->city_id,
    ]);

    $this->actingAs($foreignCityAdmin)
        ->get(route('pos.invoice.print', $invoice))
        ->assertForbidden();
});

it('blocks a cashier of another center from printing an invoice', function () {
    $center = testCenter();
    $otherCenter = testCenter();

    $invoice = Invoice::factory()->create(['center_id' => $center->id]);

    $this->actingAs(cashierFor($otherCenter))
        ->get(route('pos.invoice.print', $invoice))
        ->assertForbidden();
});

it('blocks a user of another center from downloading an expense receipt', function () {
    Storage::fake('local');

    $center = testCenter();
    $otherCenter = testCenter();

    Storage::disk('local')->put('expense-receipts/receipt.pdf', 'demo');

    $expense = Expense::factory()->create([
        'center_id' => $center->id,
        'receipt_path' => 'expense-receipts/receipt.pdf',
    ]);

    $this->actingAs(centerAdminFor($otherCenter))
        ->get(route('expenses.receipt', $expense))
        ->assertForbidden();

    // Finance is General-Admin-only in this release: even the owning
    // center's own staff are refused.
    $this->actingAs(cashierFor($center))
        ->get(route('expenses.receipt', $expense))
        ->assertForbidden();

    $this->actingAs(User::factory()->create(['role' => Roles::GeneralAdmin()]))
        ->get(route('expenses.receipt', $expense))
        ->assertOk();
});
