<?php

use App\Enums\Roles;
use App\Filament\Pages\PointOfSale;
use App\Filament\Resources\StudentResource\Pages\CreateStudentEntry;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\Street;
use App\Models\Student;
use App\Models\StudentEntry;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->center = testCenter();
    $this->staff = User::factory()->create(['role' => Roles::GeneralAdmin()]);
    $this->actingAs($this->staff);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->item = InventoryItem::factory()->create(['center_id' => $this->center->id]);
});

it('finds names across the registered name fields independently of visits', function () {
    $street = Street::create(['name' => ['en' => 'Test Street'], 'city_id' => $this->center->city_id]);

    $visiting = Student::factory()->create([
        'street_id' => $street->id,
        'firstname' => 'ريم',
        'father_name' => 'أحمد',
        'lastname' => 'أبو زيد',
    ]);
    $walkInStudent = Student::factory()->create([
        'street_id' => $street->id,
        'firstname' => 'ريم',
        'father_name' => 'محمد',
        'lastname' => 'أبو صالح',
    ]);
    StudentEntry::create([
        'student_id' => $visiting->id,
        'user_id' => $this->staff->id,
        'center_id' => $this->center->id,
        'entry_type' => 1,
        'entry_time' => now(),
    ]);

    Livewire::test(PointOfSale::class)
        ->set('centerId', $this->center->id)
        ->call('chooseBeneficiaryType', 'student')
        ->set('studentSearch', 'ريم   أحمد أبو زيد')
        ->assertSee($visiting->getFilamentName())
        ->assertDontSee($walkInStudent->getFilamentName())
        ->set('studentSearch', 'ريم أبو صالح')
        ->assertSee($walkInStudent->getFilamentName())
        ->call('selectStudent', (string) $walkInStudent->id)
        ->assertSet('beneficiaryName', $walkInStudent->getFilamentName())
        ->call('addToCart', 'i-'.$this->item->id)
        ->call('saveUnpaid')
        ->assertActionMounted('confirmNoEntry')
        ->callMountedAction();

    expect(Invoice::sole()->student_id)->toBe((string) $walkInStudent->id);
});

it('requires a choice for registered students and saves unnamed walk-in sales with a default name', function () {
    $pos = Livewire::test(PointOfSale::class)
        ->set('centerId', $this->center->id)
        ->call('addToCart', 'i-'.$this->item->id)
        ->call('chooseBeneficiaryType', 'student')
        ->call('saveUnpaid');

    expect(Invoice::count())->toBe(0);

    $pos->call('chooseBeneficiaryType', 'walk_in')
        ->call('saveUnpaid')
        ->assertActionMounted('confirmNoEntry')
        ->callMountedAction();

    $invoice = Invoice::sole();
    expect($invoice->student_id)->toBeNull()
        ->and($invoice->beneficiary_name)->toBe(__('pos.fields.walk_in'));
});

it('offers to register a visit only when the selected student has no visit today at this center', function () {
    $street = Street::create(['name' => ['en' => 'Test Street'], 'city_id' => $this->center->city_id]);
    $student = Student::factory()->create([
        'street_id' => $street->id,
        'religion' => 1,
        'nationality' => 'other',
    ]);

    $entry = [
        'student_id' => $student->id,
        'user_id' => $this->staff->id,
        'entry_type' => 1,
        'entry_time' => now(),
    ];
    StudentEntry::create($entry + ['center_id' => testCenter()->id]);

    $pos = Livewire::test(PointOfSale::class)
        ->set('centerId', $this->center->id)
        ->call('addToCart', 'i-'.$this->item->id)
        ->call('selectStudent', (string) $student->id)
        ->assertSee(__('pos.messages.student_has_no_visit'))
        ->assertSee(__('pos.actions.register_visit'))
        ->assertSee('center_id='.$this->center->id);

    $category = ServiceCategory::create(['name' => 'Daily service', 'unit' => 'day']);
    $service = Service::create(['category_id' => $category->id, 'name' => 'Internet', 'is_active' => true]);
    ServicePrice::create([
        'service_id' => $service->id,
        'center_id' => $this->center->id,
        'price' => 250,
        'is_active' => true,
        'start_date' => today(),
    ]);

    Livewire::withQueryParams(['center_id' => $this->center->id])
        ->test(CreateStudentEntry::class, ['record' => (string) $student->id])
        ->assertSet('data.center_id', $this->center->id)
        ->set('data.day_services', [$service->id])
        ->call('save')
        ->assertHasNoErrors();

    $createdVisit = StudentEntry::where('student_id', $student->id)->where('center_id', $this->center->id)->sole();
    expect($createdVisit->services()->where('service_id', $service->id)->exists())->toBeTrue();

    $pos->call('$refresh')
        ->assertSee(__('pos.messages.student_has_visit'))
        ->assertDontSee(__('pos.messages.student_has_no_visit'))
        ->assertSet('cart.i-'.$this->item->id.'.quantity', 1);
});
