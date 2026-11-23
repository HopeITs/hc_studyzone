<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Roles;
use App\Enums\UserPermission;
use App\Models\RolePermission;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;

class UserPermissions extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected string $view = 'filament.pages.user-permissions';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->role === Roles::GeneralAdmin() && (bool) $user->is_active;
    }

    public static function getNavigationLabel(): string
    {
        return __('general.user_permissions.title');
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            $this->targetField(),
            Text::make(__('general.user_permissions.view_required'))
                ->color('gray')
                ->visible(fn (): bool => $this->targetRole() !== null),
            Grid::make(['default' => 1, 'lg' => 2])
                ->visible(fn (): bool => $this->targetRole() !== null)
                ->schema([
                    $this->permissionCard('students', 'heroicon-o-academic-cap', [
                        UserPermission::ViewStudents, UserPermission::CreateStudent,
                        UserPermission::EditStudent, UserPermission::DeleteStudent,
                    ]),
                    $this->permissionCard('visits', 'heroicon-o-calendar-days', [
                        UserPermission::ViewEntries, UserPermission::CreateEntry,
                        UserPermission::EditEntry, UserPermission::DeleteEntry,
                    ]),
                ]),
            Section::make(__('general.user_permissions.catalog_title'))
                ->description(__('general.user_permissions.catalog_description'))
                ->visible(fn (): bool => $this->targetRole() !== null)
                ->schema([
                    Grid::make(['default' => 1, 'lg' => 2])->schema($this->catalogCards()),
                ]),
        ]);
    }

    protected function targetField(): Select
    {
        return Select::make('user_id')
            ->label(__('general.labels.full_name'))
            ->helperText(__('general.user_permissions.eligible_users'))
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => $this->eligibleUsers()
                ->where('name', 'like', '%'.$search.'%')
                ->orderBy('name')
                ->limit(50)
                ->get()
                ->mapWithKeys(fn (User $user): array => [$user->getKey() => $this->userLabel($user)])
                ->all())
            ->getOptionLabelUsing(function (int|string $value): ?string {
                $user = $this->eligibleUsers()->find($value);

                return $user ? $this->userLabel($user) : null;
            })
            ->required()
            ->live()
            ->afterStateUpdated(function (int|string|null $state): void {
                $this->form->fill(['user_id' => $state, ...$this->formPermissions($this->eligibleUsers()->find($state))]);
            });
    }

    private function catalogCards(): array
    {
        $groups = __('general.user_permissions.catalog');

        return array_map(fn (string $key, array $group): Section => Section::make($group['title'])
            ->columnSpan(1)
            ->collapsed()
            ->schema(array_map(fn (string $label, int $index): ToggleButtons => ToggleButtons::make("catalog_{$key}_{$index}")
                ->label($label)
                ->options([
                    'inherit' => __('general.user_permissions.inherit'),
                    'allow' => __('general.user_permissions.allow'),
                    'deny' => __('general.user_permissions.deny'),
                ])
                ->grouped()
                ->fullWidth()
                ->disabled()
                ->dehydrated(false), $group['actions'], array_keys($group['actions']))), array_keys($groups), $groups);
    }

    private function permissionCard(string $group, string $icon, array $permissions): Section
    {
        return Section::make(__('general.user_permissions.'.$group))
            ->icon($icon)
            ->columnSpan(1)
            ->schema(array_map(fn (UserPermission $permission): ToggleButtons => ToggleButtons::make($permission->value)
                ->label(__('general.user_permissions.'.$permission->value))
                ->options([
                    'inherit' => __('general.user_permissions.inherit'),
                    'allow' => __('general.user_permissions.allow'),
                    'deny' => __('general.user_permissions.deny'),
                ])
                ->colors(['inherit' => 'primary', 'allow' => 'success', 'deny' => 'danger'])
                ->grouped()
                ->fullWidth()
                ->helperText(fn (): string => __(
                    'general.user_permissions.'.($this->inheritedPermission($permission)
                        ? 'role_allows'
                        : 'role_denies')
                ))
                ->default('inherit')
                ->required(), $permissions));
    }

    public function setAllPermissions(string $choice): void
    {
        abort_unless(static::canAccess() && $this->targetRole() !== null, 403);
        abort_unless(in_array($choice, ['inherit', 'allow', 'deny'], true), 422);

        $this->form->fill([
            ...$this->targetState(),
            ...array_fill_keys(array_column(UserPermission::cases(), 'value'), $choice),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();

        $permissions = [];

        foreach (UserPermission::cases() as $permission) {
            $choice = $data[$permission->value];

            if ($choice !== 'inherit') {
                $permissions[$permission->value] = $choice === 'allow';
            }
        }

        $this->persistPermissions($data, $permissions);

        Notification::make()->success()->title(__('general.user_permissions.saved'))->send();
    }

    protected function persistPermissions(array $data, array $permissions): void
    {
        DB::transaction(function () use ($data, $permissions): void {
            $user = $this->eligibleUsers()->lockForUpdate()->findOrFail($data['user_id']);
            $user->permission_overrides = $permissions === [] ? null : [
                'role' => (int) $user->role,
                'permissions' => $permissions,
            ];
            $user->save();
        });
    }

    public function targetRole(): ?int
    {
        return $this->selectedUser ? (int) $this->selectedUser->role : null;
    }

    protected function targetState(): array
    {
        return ['user_id' => $this->selectedUser->getKey()];
    }

    protected function inheritedPermission(UserPermission $permission): bool
    {
        $role = $this->targetRole();
        $overrides = once(fn (): array => RolePermission::overridesFor((int) $this->targetRole()));

        return $overrides[$permission->value] ?? $permission->defaultFor((int) $role);
    }

    private function eligibleUsers(): Builder
    {
        abort_unless(static::canAccess(), 403);

        return User::query()->whereIn('role', [Roles::CityAdmin(), Roles::CenterAdmin()]);
    }

    #[Computed]
    public function selectedUser(): ?User
    {
        $id = $this->data['user_id'] ?? null;

        return is_int($id) || is_string($id) ? $this->eligibleUsers()->find($id) : null;
    }

    private function userLabel(User $user): string
    {
        return $user->name.' ('.$user->email.') · '.__('general.roles.'.$user->role);
    }

    private function formPermissions(?User $user): array
    {
        $overrides = $user?->permission_overrides ?? [];
        $permissions = ($overrides['role'] ?? null) === (int) $user?->role ? $overrides['permissions'] ?? [] : [];

        return $this->choicesFor($permissions);
    }

    protected function choicesFor(array $permissions): array
    {
        return collect(UserPermission::cases())->mapWithKeys(fn (UserPermission $permission): array => [
            $permission->value => match ($permissions[$permission->value] ?? null) {
                true => 'allow',
                false => 'deny',
                default => 'inherit',
            },
        ])->all();
    }
}
