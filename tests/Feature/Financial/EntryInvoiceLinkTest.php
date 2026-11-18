<?php

use App\Enums\InvoiceStatus;
use App\Enums\Roles;
use App\Filament\Pages\PointOfSale;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\StockMovement;
use App\Models\Street;
use App\Models\Student;
use App\Models\StudentEntry;
use App\Models\User;
use App\Services\EntryInvoiceService;
use App\Services\PosService;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->center = testCenter();
    $this->admin = User::factory()->create(['role' => Roles::GeneralAdmin()]);
    $this->shift = openShift($this->center);
    $street = Street::create(['name' => ['en' => 'Test Street'], 'city_id' => $this->center->city_id]);
    $this->student = Student::factory()->create(['street_id' => $street->id]);
    $this->entry = StudentEntry::create([
        'student_id' => $this->student->id,
        'center_id' => $this->center->id,
        'user_id' => $this->admin->id,
        'entry_type' => 1,
        'entry_time' => now(),
    ]);

    $category = ServiceCategory::create(['name' => 'Day service', 'unit' => 'day']);
    $this->internet = Service::create([
        'name' => 'Internet',
        'category_id' => $category->id,
        'reference_column_name' => 'use_internet',
        'is_active' => true,
    ]);
    ServicePrice::create([
        'center_id' => $this->center->id,
        'service_id' => $this->internet->id,
        'price' => 250,
        'is_active' => true,
        'start_date' => today(),
    ]);
});

it('bills an entry service once and rejects a second payment from POS', function () {
    $line = $this->entry->services()->create([
        'service_id' => $this->internet->id,
        'amount' => 1,
        'total' => 250,
        'price_at_time' => 250,
    ]);
    app(EntryInvoiceService::class)->sync($this->entry);
    $invoice = $line->refresh()->invoiceItem->invoice;
    expect($invoice->entry_id)->toBe((string) $this->entry->id)
        ->and($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->total)->toBe(250)
        ->and($invoice->shift_id)->toBe($this->shift->id);

    expect(fn () => app(PosService::class)->createInvoice(
        $this->center->id,
        [['service_id' => $this->internet->id, 'quantity' => 1]],
        'Test student',
        null,
        0,
        null,
        true,
        studentId: $this->student->id,
    ))->toThrow(ValidationException::class);
    expect(Invoice::query()->count())->toBe(1);
});

it('keeps historical entry services out of newly generated invoices', function () {
    $this->entry->services()->create([
        'service_id' => $this->internet->id,
        'amount' => 1,
        'total' => 250,
        'price_at_time' => 250,
        'pre_finance' => true,
    ]);

    app(EntryInvoiceService::class)->sync($this->entry);

    expect($this->entry->services()->count())->toBe(1)
        ->and(Invoice::query()->count())->toBe(0);
});

it('links POS internet and drinks to the visit and removes the service when cancelled', function () {
    $drink = InventoryItem::factory()->create([
        'center_id' => $this->center->id,
        'category' => EntryInvoiceService::DRINK_CATEGORY,
        'sale_price' => 100,
    ]);
    StockMovement::factory()->create([
        'center_id' => $this->center->id,
        'inventory_item_id' => $drink->id,
        'quantity' => 5,
    ]);

    $invoice = app(PosService::class)->createInvoice(
        $this->center->id,
        [
            ['service_id' => $this->internet->id, 'quantity' => 1],
            ['inventory_item_id' => $drink->id, 'quantity' => 1],
        ],
        'Test student',
        null,
        0,
        null,
        true,
        studentId: $this->student->id,
    );

    app(EntryInvoiceService::class)->sync($this->entry);
    expect($this->entry->purchasedLines()->count())->toBe(2)
        ->and($this->entry->services()->count())->toBe(1)
        ->and(Invoice::query()->count())->toBe(1)
        ->and($drink->fresh()->current_stock)->toBe(4);

    expect(fn () => $this->entry->services()->create([
        'service_id' => $this->internet->id,
        'amount' => 1,
        'total' => 250,
        'price_at_time' => 250,
    ]))->toThrow(ValidationException::class);

    $invoice->markCancelled();
    expect($this->entry->services()->count())->toBe(0)
        ->and((bool) $this->entry->fresh()->use_internet)->toBeFalse()
        ->and($drink->fresh()->current_stock)->toBe(5);
});

it('warns for every sale without a visit, including ordinary items', function () {
    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $item = InventoryItem::factory()->create(['center_id' => $this->center->id, 'category' => 'Retail']);

    $pos = Livewire::test(PointOfSale::class)
        ->set('centerId', $this->center->id)
        ->set('beneficiaryName', 'Walk-in customer')
        ->call('addToCart', 's-'.$this->internet->id)
        ->call('saveUnpaid')
        ->assertActionMounted('confirmNoEntry');

    expect(Invoice::query()->count())->toBe(0);
    $pos->callMountedAction();
    expect(Invoice::query()->count())->toBe(1);

    $drink = InventoryItem::factory()->create([
        'center_id' => $this->center->id,
        'category' => EntryInvoiceService::DRINK_CATEGORY,
    ]);
    Livewire::test(PointOfSale::class)
        ->set('centerId', $this->center->id)
        ->set('beneficiaryName', 'Walk-in customer')
        ->call('addToCart', 'i-'.$drink->id)
        ->call('saveUnpaid')
        ->assertActionMounted('confirmNoEntry');
    expect(Invoice::query()->count())->toBe(1);

    $ordinarySale = Livewire::test(PointOfSale::class)
        ->set('centerId', $this->center->id)
        ->set('beneficiaryName', 'Walk-in customer')
        ->call('addToCart', 'i-'.$item->id)
        ->call('saveUnpaid')
        ->assertActionMounted('confirmNoEntry');
    expect(Invoice::query()->count())->toBe(1);
    $ordinarySale->callMountedAction();
    expect(Invoice::query()->count())->toBe(2);

    Livewire::test(PointOfSale::class)
        ->set('centerId', $this->center->id)
        ->call('selectStudent', (string) $this->student->id)
        ->call('addToCart', 'i-'.$item->id)
        ->call('saveUnpaid')
        ->assertActionNotMounted('confirmNoEntry');
    expect(Invoice::query()->count())->toBe(3);
});
