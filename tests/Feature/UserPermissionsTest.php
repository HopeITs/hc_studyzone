<?php

declare(strict_types=1);

use App\Enums\Roles;
use App\Enums\UserPermission;
use App\Filament\Pages\RolePermissions;
use App\Filament\Pages\UserPermissions;
use App\Filament\Resources\EntryResource;
use App\Filament\Resources\StudentResource;
use App\Models\RolePermission;
use App\Models\StudentEntry;
use App\Models\User;
use Livewire\Livewire;

it('lets the general admin set individual permissions and restore the role default', function (): void {
    $this->actingAs(User::factory()->create(['role' => Roles::GeneralAdmin()]));
    $manager = User::factory()->create(['role' => Roles::CityAdmin()]);

    $this->get(route('filament.admin.pages.user-permissions'))->assertOk();

    $page = Livewire::test(UserPermissions::class)
        ->set('data.user_id', $manager->getKey())
        ->set('data.create_student', 'allow')
        ->set('data.create_entry', 'deny')
        ->call('save')
        ->assertHasNoErrors();

    $manager->refresh();
    expect($manager->hasPermission(UserPermission::CreateStudent))->toBeTrue()
        ->and($manager->hasPermission(UserPermission::CreateEntry))->toBeFalse()
        ->and($manager->hasPermission(UserPermission::ViewEntries))->toBeTrue();

    $page->set('data.create_student', 'inherit')->call('save')->assertHasNoErrors();
    expect($manager->fresh()->hasPermission(UserPermission::CreateStudent))->toBeFalse();
});

it('blocks other roles from managing permissions and applies section denials', function (): void {
    $manager = User::factory()->create(['role' => Roles::CenterAdmin()]);
    $manager->permission_overrides = [
        'role' => Roles::CenterAdmin(),
        'permissions' => ['view_students' => false, 'view_entries' => false],
    ];
    $manager->save();
    $this->actingAs($manager);

    $this->get(route('filament.admin.pages.user-permissions'))->assertForbidden();
    $this->get(route('filament.admin.resources.students.index'))->assertForbidden();
    $this->get(route('filament.admin.resources.entries.index'))->assertForbidden();
    expect(StudentResource::canCreate())->toBeFalse()
        ->and(EntryResource::canEdit(new StudentEntry))->toBeFalse();
});

it('rejects invalid choices and ineligible target accounts', function (): void {
    $admin = User::factory()->create(['role' => Roles::GeneralAdmin()]);
    $this->actingAs($admin);
    $manager = User::factory()->create(['role' => Roles::CityAdmin()]);

    Livewire::test(UserPermissions::class)
        ->set('data.user_id', $manager->getKey())
        ->set('data.create_student', 'invalid')
        ->call('save')
        ->assertHasErrors(['data.create_student']);

    expect($manager->fresh()->permission_overrides)->toBeNull();

    Livewire::test(UserPermissions::class)
        ->set('data.user_id', $admin->getKey())
        ->call('save')
        ->assertForbidden();
});

it('stages bulk edits until save and restores role defaults', function (): void {
    $this->actingAs(User::factory()->create(['role' => Roles::GeneralAdmin()]));
    $manager = User::factory()->create(['role' => Roles::CityAdmin()]);

    $page = Livewire::test(UserPermissions::class)
        ->set('data.user_id', $manager->getKey())
        ->call('setAllPermissions', 'deny')
        ->assertSet('data.view_students', 'deny')
        ->assertSet('data.delete_entry', 'deny');

    expect($manager->fresh()->permission_overrides)->toBeNull();

    $page->call('save')->assertHasNoErrors();
    expect($manager->fresh()->permission_overrides['permissions'])->toHaveCount(8);

    $page->call('setAllPermissions', 'inherit')->call('save')->assertHasNoErrors();
    expect($manager->fresh()->permission_overrides)->toBeNull();
});

it('applies a role setting to eligible users and preserves an individual choice', function (): void {
    $this->actingAs(User::factory()->create(['role' => Roles::GeneralAdmin()]));
    $manager = User::factory()->create(['role' => Roles::CityAdmin()]);
    $otherManager = User::factory()->create(['role' => Roles::CityAdmin()]);
    $centerManager = User::factory()->create(['role' => Roles::CenterAdmin()]);

    $this->get(route('filament.admin.pages.role-permissions'))->assertOk();

    $page = Livewire::test(RolePermissions::class)
        ->set('data.role_id', Roles::CityAdmin())
        ->set('data.create_student', 'allow')
        ->call('save')
        ->assertHasNoErrors();

    expect($manager->fresh()->hasPermission(UserPermission::CreateStudent))->toBeTrue()
        ->and($otherManager->fresh()->hasPermission(UserPermission::CreateStudent))->toBeTrue()
        ->and($centerManager->fresh()->hasPermission(UserPermission::CreateStudent))->toBeTrue()
        ->and(RolePermission::overridesFor(Roles::CenterAdmin()))->toBe([]);

    Livewire::test(UserPermissions::class)
        ->set('data.user_id', $manager->getKey())
        ->set('data.create_student', 'deny')
        ->call('save')
        ->assertHasNoErrors();

    expect($manager->fresh()->hasPermission(UserPermission::CreateStudent))->toBeFalse()
        ->and($otherManager->fresh()->hasPermission(UserPermission::CreateStudent))->toBeTrue();

    $page->set('data.create_student', 'inherit')->call('save')->assertHasNoErrors();
    expect($otherManager->fresh()->hasPermission(UserPermission::CreateStudent))->toBeFalse();
});

it('protects the role permissions page and rejects protected roles', function (): void {
    $this->actingAs(User::factory()->create(['role' => Roles::CenterAdmin()]));
    $this->get(route('filament.admin.pages.role-permissions'))->assertForbidden();

    $this->actingAs(User::factory()->create(['role' => Roles::GeneralAdmin()]));
    Livewire::test(RolePermissions::class)
        ->set('data.role_id', Roles::GeneralAdmin())
        ->call('save')
        ->assertForbidden();

    expect(RolePermission::query()->count())->toBe(0);
});

it('allows choosing a person directly and shows protected roles', function (): void {
    $this->actingAs(User::factory()->create(['role' => Roles::GeneralAdmin()]));
    $manager = User::factory()->create(['role' => Roles::CityAdmin()]);
    $cashier = User::factory()->create(['role' => Roles::Cashier()]);

    Livewire::test(UserPermissions::class)
        ->set('data.user_id', $manager->getKey())
        ->assertSet('data.user_id', $manager->getKey())
        ->set('data.user_id', $cashier->getKey())
        ->assertSee(__('general.user_permissions.read_only_role'));

    foreach ([Roles::Unknown(), Roles::GeneralAdmin(), Roles::MealAdmin(), Roles::Observer(), Roles::Cashier()] as $role) {
        Livewire::test(RolePermissions::class)
            ->set('data.role_id', $role)
            ->assertSet('data.role_id', $role)
            ->assertSee(__('general.user_permissions.read_only_role'))
            ->call('save')
            ->assertForbidden();
    }
});

it('switches between individual and role settings on one page', function (): void {
    $this->actingAs(User::factory()->create(['role' => Roles::GeneralAdmin()]));
    $manager = User::factory()->create(['role' => Roles::CityAdmin()]);

    Livewire::test(UserPermissions::class)
        ->set('data.user_id', $manager->getKey())
        ->set('data.view_entries', 'deny')
        ->call('switchTargetMode', 'role')
        ->assertSet('data.user_id', null)
        ->set('data.role_id', Roles::CityAdmin())
        ->assertSet('data.view_entries', 'inherit')
        ->set('data.create_student', 'allow')
        ->call('save')
        ->assertHasNoErrors()
        ->call('switchTargetMode', 'name')
        ->assertSet('data.role_id', null);

    expect($manager->fresh()->permission_overrides)->toBeNull()
        ->and($manager->fresh()->hasPermission(UserPermission::CreateStudent))->toBeTrue();
});
