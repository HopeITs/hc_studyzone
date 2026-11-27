<?php

use App\Enums\EntryType;
use App\Enums\Roles;
use App\Filament\Resources\AdmissionCategoryResource;
use App\Filament\Resources\AdmissionCategoryResource\Pages\ListAdmissionCategories;
use App\Filament\Resources\EntryResource\Pages\ListEntries;
use App\Models\AdmissionPrice;
use App\Models\LockedMonth;
use App\Models\Street;
use App\Models\Student;
use App\Models\StudentEntry;
use App\Models\User;
use App\Services\FinanceReportService;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->center = testCenter();
    $this->other = testCenter();
    $this->admin = User::factory()->create(['role' => Roles::GeneralAdmin()]);
    $street = Street::create(['name' => ['en' => 'Test Street'], 'city_id' => $this->center->city_id]);
    $this->student = Student::factory()->create(['street_id' => $street->id]);
});

function dailyEntry($test, $center, string $date): StudentEntry
{
    return StudentEntry::create([
        'student_id' => $test->student->id,
        'center_id' => $center->id,
        'user_id' => $test->admin->id,
        'entry_type' => EntryType::Daily(),
        'entry_time' => $date.' 10:00:00',
    ]);
}

it('charges the default price in every center and lets one center override it', function () {
    AdmissionPrice::schedule(null, EntryType::Daily, 100, today()->subMonth()->toDateString());
    AdmissionPrice::schedule($this->other->id, EntryType::Daily, 150, today()->subMonth()->toDateString());

    expect(AdmissionPrice::dailyFeeOn($this->center->id, today()->toDateString()))->toBe(100)
        ->and(AdmissionPrice::dailyFeeOn($this->other->id, today()->toDateString()))->toBe(150)
        ->and(dailyEntry($this, $this->center, today()->toDateString())->daily_admission_fee)->toBe(100)
        ->and(dailyEntry($this, $this->other, today()->toDateString())->daily_admission_fee)->toBe(150);
});

it('keeps the price history and closes the previous price the day before', function () {
    $first = AdmissionPrice::schedule(null, EntryType::Daily, 100, today()->subDays(20)->toDateString());
    AdmissionPrice::schedule(null, EntryType::Daily, 120, today()->addDays(5)->toDateString());

    expect(AdmissionPrice::count())->toBe(2)
        ->and($first->fresh()->ends_on->toDateString())->toBe(today()->addDays(4)->toDateString())
        ->and($first->fresh()->status())->toBe('active')
        ->and(AdmissionPrice::query()->latest('starts_on')->first()->status())->toBe('scheduled');
});

it('accepts a start date in the past and recalculates the fees recorded since', function () {
    AdmissionPrice::schedule(null, EntryType::Daily, 100, today()->subDays(20)->toDateString());
    $before = dailyEntry($this, $this->center, today()->subDays(10)->toDateString());
    $after = dailyEntry($this, $this->center, today()->subDays(3)->toDateString());
    $elsewhere = dailyEntry($this, $this->other, today()->subDays(3)->toDateString());

    AdmissionPrice::schedule($this->center->id, EntryType::Daily, 140, today()->subDays(5)->toDateString());

    expect($before->fresh()->daily_admission_fee)->toBe(100)
        ->and($after->fresh()->daily_admission_fee)->toBe(140)
        ->and($elsewhere->fresh()->daily_admission_fee)->toBe(100);
});

it('inserts a past price before a later change without overlapping it', function () {
    AdmissionPrice::schedule(null, EntryType::Daily, 100, today()->subDays(30)->toDateString());
    AdmissionPrice::schedule(null, EntryType::Daily, 120, today()->subDays(10)->toDateString());
    $entry = dailyEntry($this, $this->center, today()->subDays(15)->toDateString());

    $inserted = AdmissionPrice::schedule(null, EntryType::Daily, 110, today()->subDays(20)->toDateString());

    expect($inserted->ends_on->toDateString())->toBe(today()->subDays(11)->toDateString())
        ->and(AdmissionPrice::dailyFeeOn($this->center->id, today()->subDays(25)->toDateString()))->toBe(100)
        ->and($entry->fresh()->daily_admission_fee)->toBe(110)
        ->and(AdmissionPrice::dailyFeeOn($this->center->id, today()->toDateString()))->toBe(120);
});

it('rejects a price that would overlap a fixed period or a locked month', function () {
    AdmissionPrice::schedule(null, EntryType::Daily, 100, today()->subDays(30)->toDateString(), today()->subDays(20)->toDateString());

    expect(fn () => AdmissionPrice::schedule(null, EntryType::Daily, 90, today()->subDays(25)->toDateString()))
        ->toThrow(ValidationException::class)
        ->and(fn () => AdmissionPrice::schedule(null, EntryType::Daily, 90, today()->subDays(30)->toDateString()))
        ->toThrow(ValidationException::class);

    LockedMonth::create(['year' => today()->year, 'month' => today()->month, 'is_locked' => true, 'locked_at' => now()]);

    expect(fn () => AdmissionPrice::schedule(null, EntryType::Daily, 90, today()->toDateString()))
        ->toThrow(ValidationException::class)
        ->and(AdmissionPrice::count())->toBe(1);
});

it('cancels only a scheduled price and reopens the one before it', function () {
    $current = AdmissionPrice::schedule(null, EntryType::Monthly, 1000, today()->subDays(5)->toDateString());
    $scheduled = AdmissionPrice::schedule(null, EntryType::Monthly, 1200, today()->addDays(5)->toDateString());

    expect(fn () => $current->cancel())->toThrow(ValidationException::class);

    $scheduled->cancel();

    expect(AdmissionPrice::count())->toBe(1)
        ->and($current->fresh()->ends_on)->toBeNull();
});

it('adds a default or center price from the admission categories page', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs($this->admin);

    Livewire::test(ListAdmissionCategories::class)
        ->assertOk()
        ->callAction(CreateAction::class, [
            'entry_type' => EntryType::Daily(),
            'is_default' => true,
            'amount' => 100,
            'starts_on' => today()->subDays(3)->toDateString(),
        ])
        ->assertHasNoActionErrors()
        ->callAction(CreateAction::class, [
            'entry_type' => EntryType::Daily(),
            'is_default' => false,
            'center_id' => $this->other->id,
            'amount' => 130,
            'starts_on' => today()->toDateString(),
        ])
        ->assertHasNoActionErrors();

    expect(AdmissionPrice::whereNull('center_id')->value('amount'))->toBe(100)
        ->and(AdmissionPrice::where('center_id', $this->other->id)->value('amount'))->toBe(130);
});

it('shows admission categories to the general admin only, and no longer on the entries page', function () {
    $this->actingAs(User::factory()->create(['role' => Roles::CenterAdmin(), 'center_id' => $this->center->id]));
    expect(AdmissionCategoryResource::canViewAny())->toBeFalse();

    Filament::setCurrentPanel('admin');
    $this->actingAs($this->admin);
    expect(AdmissionCategoryResource::canViewAny())->toBeTrue();

    Livewire::test(ListEntries::class)->assertActionDoesNotExist('scheduleAdmissionPrice');
});

function monthlyEntry($test, $center, string $time): StudentEntry
{
    return StudentEntry::create([
        'student_id' => $test->student->id,
        'center_id' => $center->id,
        'user_id' => $test->admin->id,
        'entry_type' => EntryType::Monthly(),
        'entry_time' => $time,
    ]);
}

it('charges the monthly subscription on the first visit and covers the next ones', function () {
    $month = today()->startOfMonth();
    AdmissionPrice::schedule($this->center->id, EntryType::Monthly, 2000, $month->toDateString());

    $first = monthlyEntry($this, $this->center, $month->toDateString().' 09:00:00');
    $second = monthlyEntry($this, $this->center, $month->toDateString().' 15:00:00');
    $elsewhere = monthlyEntry($this, $this->other, $month->toDateString().' 16:00:00');

    expect($first->fresh()->monthly_status)->toBe('charged')
        ->and($first->fresh()->monthly_admission_fee)->toBe(2000)
        ->and($second->fresh()->monthly_status)->toBe('covered')
        ->and($second->fresh()->monthly_admission_fee)->toBeNull()
        ->and($elsewhere->fresh()->monthly_status)->toBe('charged')
        ->and($elsewhere->fresh()->monthly_admission_fee)->toBeNull();
});

it('passes the payment to the next visit when the first one is removed or moved earlier', function () {
    $month = today()->startOfMonth();
    AdmissionPrice::schedule(null, EntryType::Monthly, 1500, $month->toDateString());

    $first = monthlyEntry($this, $this->center, $month->toDateString().' 09:00:00');
    $second = monthlyEntry($this, $this->center, $month->toDateString().' 15:00:00');
    $first->delete();

    expect($second->fresh()->monthly_status)->toBe('charged')
        ->and($second->fresh()->monthly_admission_fee)->toBe(1500);

    $earlier = monthlyEntry($this, $this->center, $month->toDateString().' 08:00:00');

    expect($earlier->fresh()->monthly_status)->toBe('charged')
        ->and($second->fresh()->monthly_status)->toBe('covered');
});

it('leaves monthly visits recorded before tracking alone and covers the visits after them', function () {
    $month = today()->startOfMonth();
    AdmissionPrice::schedule(null, EntryType::Monthly, 1500, $month->toDateString());

    $old = monthlyEntry($this, $this->center, $month->toDateString().' 09:00:00');
    DB::table('student_entries')->where('id', $old->id)->update(['monthly_status' => null]);

    $later = monthlyEntry($this, $this->center, $month->toDateString().' 15:00:00');

    expect($old->fresh()->monthly_status)->toBeNull()
        ->and($later->fresh()->monthly_status)->toBe('covered');
});

it('fills the fee of paid visits when the monthly price is added later with a past date', function () {
    $month = today()->startOfMonth();
    $first = monthlyEntry($this, $this->center, $month->toDateString().' 09:00:00');

    expect($first->fresh()->monthly_admission_fee)->toBeNull();

    AdmissionPrice::schedule(null, EntryType::Monthly, 1800, $month->toDateString());

    expect($first->fresh()->monthly_admission_fee)->toBe(1800);
});

it('runs a subscription for one month from the visit that paid it', function () {
    $paid = today()->subMonths(2)->startOfMonth()->addDays(14);
    AdmissionPrice::schedule(null, EntryType::Monthly, 2000, $paid->copy()->subDay()->toDateString());

    $first = monthlyEntry($this, $this->center, $paid->toDateString().' 10:00:00');
    $inside = monthlyEntry($this, $this->center, $paid->copy()->addDays(20)->toDateString().' 10:00:00');
    $lastDay = monthlyEntry($this, $this->center, $paid->copy()->addMonth()->subDay()->toDateString().' 10:00:00');
    $renewed = monthlyEntry($this, $this->center, $paid->copy()->addMonth()->toDateString().' 10:00:00');

    expect($first->fresh()->monthly_status)->toBe('charged')
        ->and($inside->fresh()->monthly_status)->toBe('covered')
        ->and($lastDay->fresh()->monthly_status)->toBe('covered')
        ->and($renewed->fresh()->monthly_status)->toBe('charged')
        ->and($renewed->fresh()->monthly_admission_fee)->toBe(2000);
});

it('adds daily fees and paid monthly subscriptions to the financial report', function () {
    $day = today()->startOfMonth()->toDateString();
    AdmissionPrice::schedule(null, EntryType::Daily, 100, $day);
    AdmissionPrice::schedule(null, EntryType::Monthly, 2000, $day);

    dailyEntry($this, $this->center, $day);
    dailyEntry($this, $this->center, $day);
    monthlyEntry($this, $this->center, $day.' 09:00:00');
    monthlyEntry($this, $this->center, $day.' 12:00:00'); // covered: no income

    $reports = app(FinanceReportService::class);
    $filters = ['from' => $day, 'to' => today()->toDateString(), 'center_ids' => [$this->center->id]];

    expect($reports->unifiedTotals($filters)['income'])->toBe(2200)
        ->and($reports->unifiedTotals($filters + ['category' => 'admission_daily'])['income'])->toBe(200)
        ->and($reports->unifiedTotals($filters + ['category' => 'admission_monthly'])['income'])->toBe(2000)
        ->and($reports->unifiedTotals($filters + ['category' => 'خدمة يومية'])['income'])->toBe(0)
        ->and($reports->unifiedTotals($filters + ['movement' => 'expenses'])['income'])->toBe(0);

    $rows = collect($reports->unifiedRows($filters)->items());

    expect($rows)->toHaveCount(2)
        ->and($rows->sum('amount'))->toBe(2200)
        ->and($rows->firstWhere('amount', 200)->description)->toContain('2');
});
